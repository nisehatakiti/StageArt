<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Notification\PersonEmailResolver;
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
    private PersonEmailResolver $personEmailResolver;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        PersonRepositoryInterface $people,
        ProductionAuthorizationService $authorization,
        PersonEmailResolver $personEmailResolver
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
        $this->people = $people;
        $this->authorization = $authorization;
        $this->personEmailResolver = $personEmailResolver;
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

        // StageArt 稽古作成画面の参加メンバー表示修正 instruction: listing/
        // viewing Participants is a Read operation, gated by
        // canReadParticipants() (Participant.Read) - not
        // canManageParticipants() (Participant.Update), which stays the
        // gate for Create/Update/Cancel below. This is what admits a
        // REHEARSAL_MANAGER delegate (who has Participant.Read but not
        // Participant.Update) without giving them any write access.
        if (! $this->authorization->canReadParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER or REHEARSAL_MANAGER Role can view Participants.'
            );
        }

        // R14-B stage 6 (§7.3, D11 / U3): each row is returned only as far
        // as ParticipantDisclosurePolicy allows this viewer; a READER does
        // not get the ended rows at all. A Person's name / email is looked
        // up only for rows that will show it.
        $viewerKind = (string) ParticipantDisclosurePolicy::viewerOf($this->authorization, $requester, $production);

        /** @var array<int, array{0: Participant, 1: ParticipantDisclosure}> $visible */
        $visible = [];
        foreach ($this->participants->findByProductionId($production->id()) as $participant) {
            $disclosure = ParticipantDisclosurePolicy::disclosureFor($participant, $production, $viewerKind, $requester);

            if ($disclosure !== null) {
                $visible[] = [$participant, $disclosure];
            }
        }

        /** @var array<string, ?Person> $peopleById */
        $peopleById = [];
        /** @var array<string, ?string> $emailsByPersonId */
        $emailsByPersonId = [];
        foreach ($visible as [$participant, $disclosure]) {
            if (! $participant->subjectType()->equals(ParticipantSubjectType::person())) {
                continue;
            }

            $personId = $participant->subjectId();

            if ($disclosure->names && ! array_key_exists($personId, $peopleById)) {
                $peopleById[$personId] = $this->people->findById(PersonId::fromString($personId));
            }

            if ($disclosure->email && ! array_key_exists($personId, $emailsByPersonId)) {
                $emailsByPersonId[$personId] = $this->personEmailResolver->resolve(PersonId::fromString($personId));
            }
        }

        return array_map(
            static fn (array $row): ParticipantResult => ParticipantResult::disclosed(
                $row[0],
                $row[1],
                $peopleById[$row[0]->subjectId()] ?? null,
                $emailsByPersonId[$row[0]->subjectId()] ?? null
            ),
            $visible
        );
    }
}
