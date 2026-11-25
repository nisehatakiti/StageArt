<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * `DELETE /participants/{id}` - a manager removes a member (CANCELLED; the
 * row, attendance and accounting records are kept).
 *
 * PR #7 R14-B stage 4 (design doc §5.2 / §7.1, N10):
 * - ACTIVE / INACTIVE -> CANCELLED. In the same transaction the Person's
 *   delegate rows still ACTIVE in this Production become INACTIVE
 *   (PARTICIPANT_REMOVED - for INACTIVE this catches rows left ACTIVE
 *   before R14-B), and REMOVED_BY_MANAGER is audited.
 * - CANCELLED -> idempotent success with no side effect (not saved, no
 *   audit, delegates untouched).
 * - PENDING / DRAFT / REJECTED / WITHDRAWN -> 409
 *   `stageart_participant_cancel_not_allowed`, nothing changed.
 * - Locked Production -> Participant -> delegate (§5.4); a failure of the
 *   delegate write or the audit write rolls everything back.
 */
final class CancelParticipantUseCase
{
    private ParticipantRepositoryInterface $participants;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ParticipantDelegateRoleReleaser $releaser;
    private ParticipantEventRecorder $events;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ParticipantDelegateRoleReleaser $releaser,
        ParticipantEventRecorder $events,
        TransactionManagerInterface $transactions
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->releaser = $releaser;
        $this->events = $events;
        $this->transactions = $transactions;
    }

    public function execute(CancelParticipantCommand $command): void
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $participantId = ParticipantId::fromString($command->participantId);
        $participant = $this->participants->findById($participantId);

        if (! $participant) {
            throw new ParticipantNotFoundException($command->participantId);
        }

        $production = $this->productions->findById($participant->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($participant->productionId()->toString());
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can cancel this Participant.'
            );
        }

        $this->transactions->run(function () use ($production, $participantId, $requester): void {
            if ($this->productions->findByIdForUpdate($production->id()) === null) {
                throw new ProductionNotFoundException($production->id()->toString());
            }

            $locked = $this->participants->findByIdForUpdate($participantId);

            if ($locked === null) {
                throw new ParticipantNotFoundException($participantId->toString());
            }

            $previous = $locked->status()->toString();

            if ($previous === ParticipantStatus::CANCELLED) {
                return;
            }

            if ($previous !== ParticipantStatus::ACTIVE && $previous !== ParticipantStatus::INACTIVE) {
                throw new ParticipantCancelNotAllowedException(
                    "A {$previous} member cannot be removed; a pending join request is ended by rejecting it."
                );
            }

            $locked->cancel();
            $this->participants->save($locked);
            $now = new DateTimeImmutable();

            if ($locked->subjectType()->equals(ParticipantSubjectType::person())) {
                $this->releaser->release(
                    $locked->productionId(),
                    PersonId::fromString($locked->subjectId()),
                    $requester->id(),
                    ParticipantDelegateRoleReleaser::PARTICIPANT_REMOVED,
                    $locked,
                    $now
                );
            }

            $this->events->removedByManager($locked, $previous, $requester->id(), $now);
        });
    }
}
