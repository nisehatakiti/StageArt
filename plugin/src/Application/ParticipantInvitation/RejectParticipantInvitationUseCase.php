<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Participant\ParticipantConsentNotifier;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * R14-B §4.3 / §5.1 / §7.2 `POST /participant-invitations/{id}/reject`
 * (U7): the invitee themselves declines. A rejection never creates or
 * changes a Participant row and grants nothing.
 *
 * - Same recipient rules as accept: 403 for an account that cannot answer,
 *   404 for an invitation that is missing or not addressed to the Person.
 * - Locks: Production -> invitation (§5.4).
 * - Already REJECTED by this Person: the first result again (no write, no
 *   audit event, no notification).
 * - Allowed within the expiry from PENDING (also when the Production is
 *   closed or the inviter lost permission) and from
 *   CANCELLED(PRODUCTION_CLOSED / INVITER_REVOKED), whose close_reason /
 *   closed_at are kept. CANCELLED(MANAGER), a reason-less (pre-R14-B)
 *   CANCELLED, ACCEPTED and CONSUMED: 409 not_pending. Past the expiry: 410.
 * - On a PENDING invitation, a closed Production / an inviter without
 *   permission detected here for the first time is recorded as the
 *   close_reason / closed_at (and INVITATION_CLOSED). For INVITER_REVOKED,
 *   revocation_notified_at is set only while NULL - then the rejection
 *   notification also carries the revocation note and goes to the
 *   PrimaryManager, attempted once (§11.5). Once already recorded, no
 *   second revocation notification is attempted.
 * - After the commit the rejection is notified to the inviter, or to the
 *   PrimaryManager when the inviter no longer has permission, with the name
 *   the manager typed when inviting.
 */
final class RejectParticipantInvitationUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private InvitationRecipientVerifier $recipients;
    private ParticipantInvitationConditions $conditions;
    private ParticipantEventRecorder $events;
    private ParticipantConsentNotifier $notifier;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        InvitationRecipientVerifier $recipients,
        ParticipantInvitationConditions $conditions,
        ParticipantEventRecorder $events,
        ParticipantConsentNotifier $notifier,
        TransactionManagerInterface $transactions
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->recipients = $recipients;
        $this->conditions = $conditions;
        $this->events = $events;
        $this->notifier = $notifier;
        $this->transactions = $transactions;
    }

    public function execute(RejectParticipantInvitationCommand $command): RejectParticipantInvitationResult
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

            if ($status === ParticipantInvitationStatus::REJECTED) {
                if ($invitation->inviteePersonId() !== null && $invitation->inviteePersonId()->equals($person->id())) {
                    return ['changed' => false, 'invitation' => $invitation, 'production' => $production];
                }

                throw new ParticipantInvitationNotPendingException('The participant invitation is no longer pending.');
            }

            $closedBySystem = $status === ParticipantInvitationStatus::CANCELLED
                && $invitation->closeReason() !== null
                && $invitation->closeReason()->allowsInviteeRejection();

            if ($status !== ParticipantInvitationStatus::PENDING && ! $closedBySystem) {
                throw new ParticipantInvitationNotPendingException('The participant invitation is no longer pending.');
            }

            if ($invitation->isExpiredAt($now)) {
                throw new ParticipantInvitationExpiredException('The participant invitation has expired.');
            }

            $inviterCanManage = $this->conditions->inviterCanManage($invitation, $production);
            $firstDetected = null;

            if ($status === ParticipantInvitationStatus::PENDING) {
                if ($this->conditions->isProductionClosed($production)) {
                    $firstDetected = ParticipantInvitationCloseReason::productionClosed();
                } elseif (! $inviterCanManage) {
                    $firstDetected = ParticipantInvitationCloseReason::inviterRevoked();
                }
            }

            $invitation->reject($person->id(), $firstDetected, $now);
            $this->invitations->save($invitation);

            $revocationNotice = false;

            if ($firstDetected !== null) {
                $revocationNotice = $firstDetected->equals(ParticipantInvitationCloseReason::inviterRevoked())
                    && $this->invitations->markRevocationNotificationAttempted($invitation->id(), $now);
                $this->events->invitationClosed($invitation, 'REJECT', $revocationNotice, $person->id(), $now);
            }

            $this->events->invitationRejected($invitation, $person->id(), $status, $now);

            return [
                'changed' => true,
                'invitation' => $invitation,
                'production' => $production,
                'recipient' => $inviterCanManage ? $invitation->invitedByPersonId() : $production->primaryManagerPersonId(),
                'revocationNotice' => $revocationNotice,
            ];
        });

        /** @var ParticipantInvitation $invitation */
        $invitation = $outcome['invitation'];

        if ($outcome['changed']) {
            $this->notifier->invitationRejected($invitation, $outcome['production'], $outcome['recipient'], $outcome['revocationNotice']);
        }

        return new RejectParticipantInvitationResult(
            $invitation->id()->toString(),
            ParticipantInvitationStatus::REJECTED,
            $invitation->closeReason() !== null ? $invitation->closeReason()->toString() : null
        );
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
