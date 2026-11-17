<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * `POST /participant-invitations/{id}/cancel` - a manager withdraws an
 * invitation. No Participant is involved; the row is kept.
 *
 * PR #7 R14-B stage 4 review fix (design doc §5.1 / §5.3 / §5.4 / §7.1):
 * - PENDING (an expired one included) -> CANCELLED with close_reason
 *   MANAGER, closed_at, closed_by_person_id = the cancelling manager and
 *   updated_at (ParticipantInvitation::close(), never the legacy cancel()),
 *   audited INVITATION_CANCELLED.
 * - Already CANCELLED(MANAGER) -> the first result again (idempotent): not
 *   saved, nothing audited.
 * - CANCELLED(PRODUCTION_CLOSED / INVITER_REVOKED), a reason-less
 *   (pre-R14-B) CANCELLED, ACCEPTED, REJECTED, CONSUMED -> 409
 *   `stageart_participant_invitation_not_pending`, nothing changed.
 * - Authorization (canManageParticipants) is checked first, as before; then
 *   one transaction locks the Production and the invitation (§5.4) and
 *   decides on the locked row, so a concurrent acceptance and cancellation
 *   are serialized. A failed save or audit write rolls everything back.
 * - No notification (§11.1 defines none for a cancellation).
 */
final class CancelParticipantInvitationUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ParticipantEventRecorder $events;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ParticipantEventRecorder $events,
        TransactionManagerInterface $transactions
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->events = $events;
        $this->transactions = $transactions;
    }

    public function execute(CancelParticipantInvitationCommand $command): ParticipantInvitationResult
    {
        $invitationId = ParticipantInvitationId::fromString($command->invitationId);
        $invitation = $this->invitations->findById($invitationId);

        if (! $invitation) {
            throw new ParticipantInvitationNotFoundException($command->invitationId);
        }

        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantInvitationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById($invitation->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($invitation->productionId()->toString());
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantInvitationAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can cancel this invitation.'
            );
        }

        $cancelled = $this->transactions->run(function () use ($production, $invitationId, $requester): ParticipantInvitation {
            if ($this->productions->findByIdForUpdate($production->id()) === null) {
                throw new ProductionNotFoundException($production->id()->toString());
            }

            $locked = $this->invitations->findByIdForUpdate($invitationId);

            if ($locked === null) {
                throw new ParticipantInvitationNotFoundException($invitationId->toString());
            }

            if (self::isCancelledByManager($locked)) {
                return $locked;
            }

            if (! $locked->isPending()) {
                throw new ParticipantInvitationNotPendingException('Only a PENDING invitation can be cancelled.');
            }

            $now = new DateTimeImmutable();
            $locked->close(ParticipantInvitationCloseReason::manager(), $requester->id(), $now);
            $this->invitations->save($locked);
            $this->events->invitationCancelled($locked, $requester->id(), $now);

            return $locked;
        });

        return ParticipantInvitationResult::fromDomain($cancelled);
    }

    private static function isCancelledByManager(ParticipantInvitation $invitation): bool
    {
        return $invitation->status()->toString() === ParticipantInvitationStatus::CANCELLED
            && $invitation->closeReason() !== null
            && $invitation->closeReason()->toString() === ParticipantInvitationCloseReason::MANAGER;
    }
}
