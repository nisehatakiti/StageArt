<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * `POST /productions/{id}/participants` - NAME_ONLY members only.
 *
 * PR #7 R14-B stage 4 (design doc §4.4 / §5.2 / §7.1 / §7.4, D10):
 * - A PERSON or an ORGANIZATION can no longer be added directly: 422
 *   `stageart_participant_requires_invitation`. A Person becomes a
 *   participant only by accepting an invitation or by an approved join
 *   request (ParticipantEnrollmentService); no Person or Organization is
 *   looked up here.
 * - NAME_ONLY (a manager's record of someone without a StageArt account)
 *   is kept: enrollment_source MANAGER_RECORD, no consent; one
 *   transaction, Production locked first (§5.4), NAME_ONLY_ADDED audited.
 * - Authorization (canManageParticipants) is unchanged and decided first.
 */
final class CreateParticipantUseCase
{
    private ProductionRepositoryInterface $productions;
    private ParticipantRepositoryInterface $participants;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;
    private ParticipantEventRecorder $events;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ParticipantRepositoryInterface $participants,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions,
        ParticipantEventRecorder $events
    ) {
        $this->productions = $productions;
        $this->participants = $participants;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
        $this->events = $events;
    }

    public function execute(CreateParticipantCommand $command): ParticipantResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can manage Participants.'
            );
        }

        $subjectType = ParticipantSubjectType::fromString($command->subjectType);

        if (! $subjectType->equals(ParticipantSubjectType::nameOnly())) {
            throw new ParticipantRequiresInvitationException(
                'A person or an organization cannot be added directly; invite them instead.'
            );
        }

        $participantType = ParticipantType::fromString($command->participantType);

        if ($command->displayName === null || trim($command->displayName) === '') {
            throw new ParticipantSubjectNotEligibleException('displayName is required for a NAME_ONLY Participant.');
        }

        $participant = $this->transactions->run(
            function () use ($production, $command, $participantType, $requester): Participant {
                if ($this->productions->findByIdForUpdate($production->id()) === null) {
                    throw new ProductionNotFoundException($production->id()->toString());
                }

                $participant = Participant::createNameOnly($production->id(), $command->displayName, $participantType, $command->remarks);
                $this->participants->save($participant);
                $this->events->nameOnlyAdded($participant, $requester->id(), new DateTimeImmutable());

                return $participant;
            }
        );

        return ParticipantResult::fromDomain($participant);
    }
}
