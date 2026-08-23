<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * DELETE is always a Status change to CANCELLED, never a physical
 * delete: Participant.md states "Participantは原則として物理削除しない."
 */
final class CancelParticipantUseCase
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

    public function execute(CancelParticipantCommand $command): void
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $participant = $this->participants->findById(ParticipantId::fromString($command->participantId));

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

        $participant->cancel();
        $this->participants->save($participant);
    }
}
