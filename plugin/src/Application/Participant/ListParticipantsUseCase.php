<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class ListParticipantsUseCase
{
    private ParticipantRepositoryInterface $participants;
    private ProductionRepositoryInterface $productions;
    private PersonRepositoryInterface $people;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        PersonRepositoryInterface $people,
        ProductionAuthorizationService $authorization
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
        $this->people = $people;
        $this->authorization = $authorization;
    }

    /**
     * @return ParticipantResult[]
     */
    public function execute(ListParticipantsQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can view Participants.'
            );
        }

        $participants = $this->participants->findByProductionId($production->id());

        $personIds = array_values(array_unique(array_map(
            static fn (Participant $participant): string => $participant->subjectId(),
            array_filter($participants, static fn (Participant $participant): bool => $participant->subjectType()->equals(ParticipantSubjectType::person()))
        )));

        /** @var array<string, Person> $peopleById */
        $peopleById = [];
        foreach ($personIds as $personId) {
            $person = $this->people->findById(PersonId::fromString($personId));
            if ($person) {
                $peopleById[$personId] = $person;
            }
        }

        return array_map(
            fn (Participant $participant): ParticipantResult => ParticipantResult::fromDomain(
                $participant,
                $peopleById[$participant->subjectId()] ?? null
            ),
            $participants
        );
    }
}
