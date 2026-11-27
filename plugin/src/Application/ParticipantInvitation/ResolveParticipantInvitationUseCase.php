<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use StageArt\Application\Participant\ParticipantConsentNotifier;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Application\Person\AmbiguousPersonEmailException;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use Throwable;

/**
 * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27):
 * the Production counterpart of ResolveOrganizationInvitationUseCase. Once
 * an address has been verified (VerifyEmailUseCase, the organization
 * invitation's registration) or signed in with Google
 * (AuthenticateWithGoogleUseCase), every usable invitation to that address
 * registers the Person as a Participant - no acceptance by the person, no
 * consent recorded. Called internally only, never exposed as a REST route.
 *
 * - The caller passes an address verified for this Person (the
 *   EmailCredential just verified, or the e-mail Google verified). Nothing
 *   is registered when that address is a verified address of ANOTHER
 *   Person, or of several (FindPersonByEmailUseCase) - the address is not
 *   this Person's alone.
 * - Each invitation runs in its own transaction, locked Production ->
 *   invitation (R14-B §5.4). Only a PENDING invitation within its expiry is
 *   used: a cancelled, expired or already completed one registers nothing,
 *   so running this twice never registers twice.
 * - The Production closed / the inviter without permission: the invitation
 *   is closed with that reason, as the R14-B acceptance did (the
 *   PrimaryManager is told once about INVITER_REVOKED, after the commit).
 * - Already an ACTIVE participant of the type: the invitation is completed
 *   without a change to the row. A DRAFT row of the type: nothing changes
 *   and the invitation stays PENDING (logged).
 * - A failure on one invitation is logged (ids only) and never stops the
 *   others or the verification / sign-in this runs after.
 */
final class ResolveParticipantInvitationUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private ParticipantInvitationConditions $conditions;
    private InvitationEnrollmentService $enrollment;
    private ParticipantEventRecorder $events;
    private ParticipantConsentNotifier $notifier;
    private FindPersonByEmailUseCase $findPersonByEmail;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        ParticipantInvitationConditions $conditions,
        InvitationEnrollmentService $enrollment,
        ParticipantEventRecorder $events,
        ParticipantConsentNotifier $notifier,
        FindPersonByEmailUseCase $findPersonByEmail,
        TransactionManagerInterface $transactions
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->conditions = $conditions;
        $this->enrollment = $enrollment;
        $this->events = $events;
        $this->notifier = $notifier;
        $this->findPersonByEmail = $findPersonByEmail;
        $this->transactions = $transactions;
    }

    public function execute(PersonId $personId, string $email): void
    {
        try {
            $owner = $this->findPersonByEmail->execute($email);
        } catch (AmbiguousPersonEmailException $exception) {
            return;
        }

        if ($owner !== null && $owner->id !== $personId->toString()) {
            return;
        }

        foreach ($this->invitations->findByVerifiedEmailCandidates([$email]) as $invitation) {
            if (! $invitation->isUsable()) {
                continue;
            }

            try {
                $this->resolveOne($invitation, $personId);
            } catch (Throwable $exception) {
                error_log(sprintf(
                    '[StageArt ParticipantInvitation] could not register from invitation=%s for person=%s (%s)',
                    $invitation->id()->toString(),
                    $personId->toString(),
                    get_class($exception)
                ));
            }
        }
    }

    private function resolveOne(ParticipantInvitation $candidate, PersonId $personId): void
    {
        $outcome = $this->transactions->run(function () use ($candidate, $personId): array {
            $now = new DateTimeImmutable();
            $production = $this->productions->findByIdForUpdate($candidate->productionId());
            $invitation = $this->invitations->findByIdForUpdate($candidate->id());

            if ($production === null || $invitation === null || ! $invitation->isPending() || $invitation->isExpiredAt($now)) {
                return ['kind' => 'skipped'];
            }

            $reason = null;

            if ($this->conditions->isProductionClosed($production)) {
                $reason = ParticipantInvitationCloseReason::productionClosed();
            } elseif (! $this->conditions->inviterCanManage($invitation, $production)) {
                $reason = ParticipantInvitationCloseReason::inviterRevoked();
            }

            if ($reason !== null) {
                $invitation->close($reason, null, $now);
                $this->invitations->save($invitation);
                $notify = $reason->equals(ParticipantInvitationCloseReason::inviterRevoked())
                    && $this->invitations->markRevocationNotificationAttempted($invitation->id(), $now);
                $this->events->invitationClosed($invitation, 'ENROLL', $notify, $personId, $now);

                return ['kind' => 'closed', 'notify' => $notify, 'invitation' => $invitation, 'production' => $production];
            }

            $enrolled = $this->enrollment->enroll(
                $production->id(),
                $personId,
                $invitation->participantType(),
                $invitation->invitedName(),
                $invitation->remarks(),
                $invitation,
                $personId,
                InvitationEnrollmentService::TRIGGER_ADDRESS_VERIFIED,
                $now
            );

            $invitation->completeByEnrollment($personId, $enrolled->participant->id(), $now);
            $this->invitations->save($invitation);

            return ['kind' => 'enrolled'];
        });

        if ($outcome['kind'] === 'closed' && $outcome['notify']) {
            $this->notifier->invitationClosedByRevokedInviter($outcome['invitation'], $outcome['production']);
        }
    }
}
