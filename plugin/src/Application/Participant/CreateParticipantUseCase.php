<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;

final class CreateParticipantUseCase
{
    private ProductionRepositoryInterface $productions;
    private ParticipantRepositoryInterface $participants;
    private PersonRepositoryInterface $people;
    private OrganizationRepositoryInterface $organizations;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ParticipantRepositoryInterface $participants,
        PersonRepositoryInterface $people,
        OrganizationRepositoryInterface $organizations,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->participants = $participants;
        $this->people = $people;
        $this->organizations = $organizations;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
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
        $this->assertSubjectExists($subjectType, $command->subjectId);

        $participantType = ParticipantType::fromString($command->participantType);

        if ($this->participants->findByProductionAndSubject(
            $production->id(),
            $subjectType,
            $command->subjectId,
            $participantType
        )) {
            throw new ParticipantAlreadyExistsException(
                'A Participant with this ParticipantType already exists for this Subject on this Production.'
            );
        }

        $participant = $this->transactions->run(
            function () use ($production, $subjectType, $command, $participantType): Participant {
                $participant = Participant::create($production->id(), $subjectType, $command->subjectId, $participantType);
                $this->participants->save($participant);

                return $participant;
            }
        );

        return ParticipantResult::fromDomain($participant);
    }

    private function assertSubjectExists(ParticipantSubjectType $subjectType, string $subjectId): void
    {
        if ($subjectType->equals(ParticipantSubjectType::person())) {
            if (! $this->people->findById(PersonId::fromString($subjectId))) {
                throw new ParticipantSubjectNotEligibleException('The Subject Person does not exist.');
            }

            return;
        }

        if (! $this->organizations->findById(OrganizationId::fromString($subjectId))) {
            throw new ParticipantSubjectNotEligibleException('The Subject Organization does not exist.');
        }
    }
}
