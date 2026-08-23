<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class ListParticipantsUseCase
{
    private ParticipantRepositoryInterface $participants;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
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

        return array_map(
            static fn (Participant $participant): ParticipantResult => ParticipantResult::fromDomain($participant),
            $this->participants->findByProductionId($production->id())
        );
    }
}
