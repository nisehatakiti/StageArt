<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Participant\ParticipantConsentNotifier;
use StageArt\Application\Participant\ParticipantDelegateRoleReleaser;
use StageArt\Application\Participant\ParticipantEnrollmentService;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * R14-B §5.1 / §5.2 / §7.2 `POST /participant-invitations/{id}/accept`:
 * the invitee themselves accepts. Only an explicit call by the logged-in
 * Person accepts (no auto-resolution, no token).
 *
 * 1. The Person, an ACTIVE UserAccount and at least one verified address
 *    are required (403 otherwise; nothing about the invitation is said).
 * 2. One transaction, locks in the §5.4 order: Production -> invitation ->
 *    Participant -> delegate.
 * 3. An invitation that does not exist or is not addressed to the Person:
 *    404 (the same answer, before any state is looked at).
 * 4. Already ACCEPTED by this Person: the first result again - no write,
 *    no audit event, no notification.
 * 5. CANCELLED(PRODUCTION_CLOSED / INVITER_REVOKED): 409 closed; other
 *    non-PENDING: 409 not_pending; past its expiry: 410.
 * 6. The Production closed / the inviter without permission (first
 *    detected here): the invitation becomes CANCELLED with that reason and
 *    time, INVITATION_CLOSED is recorded and this is COMMITTED before the
 *    409 is returned. For INVITER_REVOKED, revocation_notified_at is set
 *    only while still NULL; only then is the PrimaryManager notified, once,
 *    after the commit (§11.5).
 * 7. ParticipantEnrollmentService creates / reuses / records consent on the
 *    one row of the key. A DRAFT row of the same type: 409 and the whole
 *    transaction is rolled back (the invitation stays PENDING).
 * 8. When the Person thereby became an ACTIVE participant (this row was not
 *    ACTIVE and they hold no ACTIVE row of the other type), their ACTIVE
 *    delegate rows become INACTIVE (DORMANT_ON_ENROLLMENT); an already
 *    ACTIVE Participant that only records consent keeps them (U15).
 * 9. The invitation becomes ACCEPTED (invitee, responded_at, participant);
 *    ACCEPTED (and CONSENT_RECORDED for an already ACTIVE Participant) is
 *    recorded.
 * 10. After the commit, the inviter is notified (a failure there changes
 *    nothing).
 *
 * Any failed write throws and rolls everything back - no success is
 * returned without the rows.
 */
final class AcceptParticipantInvitationUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private InvitationRecipientVerifier $recipients;
    private ParticipantInvitationConditions $conditions;
    private ParticipantEnrollmentService $enrollment;
    private ParticipantDelegateRoleReleaser $delegateRoles;
    private ParticipantEventRecorder $events;
    private ParticipantConsentNotifier $notifier;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        InvitationRecipientVerifier $recipients,
        ParticipantInvitationConditions $conditions,
        ParticipantEnrollmentService $enrollment,
        ParticipantDelegateRoleReleaser $delegateRoles,
        ParticipantEventRecorder $events,
        ParticipantConsentNotifier $notifier,
        TransactionManagerInterface $transactions
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->recipients = $recipients;
        $this->conditions = $conditions;
        $this->enrollment = $enrollment;
        $this->delegateRoles = $delegateRoles;
        $this->events = $events;
        $this->notifier = $notifier;
        $this->transactions = $transactions;
    }

    public function execute(AcceptParticipantInvitationCommand $command): AcceptParticipantInvitationResult
    {
        $recipient = $this->recipients->recipientWithVerifiedAddress($command->requestedByWordPressUserId);
        $invitationId = self::invitationId($command->invitationId);
        $unlocked = $this->invitations->findById($invitationId);

        if ($unlocked === null || ! $this->recipients->isAddressedTo($recipient, $unlocked)) {
            throw new ParticipantInvitationNotFoundException('Participant invitation not found.');
        }

        $productionId = $unlocked->productionId();
        $person = $recipient->person();
        $now = new DateTimeImmutable();

        $outcome = $this->transactions->run(function () use ($invitationId, $productionId, $recipient, $person, $now): array {
            $production = $this->productions->findByIdForUpdate($productionId);
            $invitation = $this->invitations->findByIdForUpdate($invitationId);

            if ($production === null || $invitation === null || ! $this->recipients->isAddressedTo($recipient, $invitation)) {
                throw new ParticipantInvitationNotFoundException('Participant invitation not found.');
            }

            $status = $invitation->status()->toString();

            if ($status === ParticipantInvitationStatus::ACCEPTED) {
                if ($invitation->inviteePersonId() !== null && $invitation->inviteePersonId()->equals($person->id()) && $invitation->participantId() !== null) {
                    return ['kind' => 'already', 'invitation' => $invitation, 'production' => $production];
                }

                throw new ParticipantInvitationNotPendingException('The participant invitation is no longer pending.');
            }

            if ($status === ParticipantInvitationStatus::CANCELLED
                && $invitation->closeReason() !== null
                && $invitation->closeReason()->allowsInviteeRejection()
            ) {
                throw new ParticipantInvitationClosedException($invitation->closeReason()->toString());
            }

            if ($status !== ParticipantInvitationStatus::PENDING) {
                throw new ParticipantInvitationNotPendingException('The participant invitation is no longer pending.');
            }

            if ($invitation->isExpiredAt($now)) {
                throw new ParticipantInvitationExpiredException('The participant invitation has expired.');
            }

            if ($this->conditions->isProductionClosed($production)) {
                return $this->close($invitation, $production, ParticipantInvitationCloseReason::productionClosed(), $person->id(), $now);
            }

            if (! $this->conditions->inviterCanManage($invitation, $production)) {
                return $this->close($invitation, $production, ParticipantInvitationCloseReason::inviterRevoked(), $person->id(), $now);
            }

            $enrolled = $this->enrollment->enrollFromInvitation(
                $production->id(),
                $person->id(),
                $invitation->participantType(),
                $now,
                $invitation->invitedName(),
                $invitation->remarks()
            );
            $participant = $enrolled->participant;

            if ($enrolled->personBecameParticipant) {
                $this->delegateRoles->release(
                    $production->id(),
                    $person->id(),
                    $person->id(),
                    ParticipantDelegateRoleReleaser::DORMANT_ON_ENROLLMENT,
                    $participant,
                    $now
                );
            }

            $invitation->accept($person->id(), $participant->id(), $now);
            $this->invitations->save($invitation);

            $this->events->invitationAccepted($invitation, $participant, $enrolled->previousStatus, $now);

            if ($enrolled->previousStatus === ParticipantStatus::ACTIVE) {
                $this->events->consentRecorded($invitation, $participant, $now);
            }

            return ['kind' => 'accepted', 'invitation' => $invitation, 'production' => $production];
        });

        /** @var ParticipantInvitation $invitation */
        $invitation = $outcome['invitation'];
        $production = $outcome['production'];

        if ($outcome['kind'] === 'closed') {
            if ($outcome['notify']) {
                $this->notifier->invitationClosedByRevokedInviter($invitation, $production);
            }

            throw new ParticipantInvitationClosedException($outcome['reason']);
        }

        if ($outcome['kind'] === 'accepted') {
            $this->notifier->invitationAccepted($invitation, $production);
        }

        return new AcceptParticipantInvitationResult(
            $invitation->participantId()->toString(),
            $invitation->productionId()->toString(),
            ParticipantStatus::ACTIVE
        );
    }

    /**
     * Step 6: the invalidation is the transaction's result (committed), the
     * 409 is thrown by the caller after the commit.
     *
     * @return array<string, mixed>
     */
    private function close(
        ParticipantInvitation $invitation,
        Production $production,
        ParticipantInvitationCloseReason $reason,
        PersonId $actor,
        DateTimeImmutable $now
    ): array {
        $invitation->close($reason, null, $now);
        $this->invitations->save($invitation);

        $notify = $reason->equals(ParticipantInvitationCloseReason::inviterRevoked())
            && $this->invitations->markRevocationNotificationAttempted($invitation->id(), $now);

        $this->events->invitationClosed($invitation, 'ACCEPT', $notify, $actor, $now);

        return [
            'kind' => 'closed',
            'invitation' => $invitation,
            'production' => $production,
            'reason' => $reason->toString(),
            'notify' => $notify,
        ];
    }

    private static function invitationId(string $value): ParticipantInvitationId
    {
        try {
            return ParticipantInvitationId::fromString($value);
        } catch (InvalidArgumentException $exception) {
            throw new ParticipantInvitationNotFoundException('Participant invitation not found.');
        }
    }
}
