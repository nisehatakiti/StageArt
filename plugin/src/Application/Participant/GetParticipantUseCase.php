<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class GetParticipantUseCase
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

    public function execute(GetParticipantQuery $query): ParticipantResult
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $participant = $this->participants->findById(ParticipantId::fromString($query->participantId));

        if (! $participant) {
            throw new ParticipantNotFoundException($query->participantId);
        }

        $production = $this->productions->findById($participant->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($participant->productionId()->toString());
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can view this Participant.'
            );
        }

        return ParticipantResult::fromDomain($participant);
    }
}
