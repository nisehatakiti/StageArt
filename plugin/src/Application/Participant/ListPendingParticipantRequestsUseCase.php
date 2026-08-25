<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/** docs/04-HomeRoleBasedMenu.md's "Production参加申請（2件）" - the
 * Production manager's approval queue. */
final class ListPendingParticipantRequestsUseCase
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
     * @return ParticipantRequestResult[]
     */
    public function execute(ListPendingParticipantRequestsQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);
        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productions->findById($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $person || ! $this->authorization->canManageParticipants($person, $production)) {
            throw new ParticipantAccessDeniedException('Only a Production manager can view pending Participant requests.');
        }

        $pending = array_filter(
            $this->participants->findByProductionId($productionId),
            static fn (Participant $participant): bool => $participant->isPending()
        );

        return array_values(array_map(
            fn (Participant $participant) => ParticipantRequestResult::fromDomain(
                $participant,
                $this->people->findById(PersonId::fromString($participant->subjectId()))
            ),
            $pending
        ));
    }
}
