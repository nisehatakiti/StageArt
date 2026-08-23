<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class UpdateParticipantUseCase
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

    public function execute(UpdateParticipantCommand $command): ParticipantResult
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
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can update this Participant.'
            );
        }

        $participant->changeParticipantType(ParticipantType::fromString($command->participantType));
        $participant->changeStatus(ParticipantStatus::fromString($command->status));

        $this->participants->save($participant);

        return ParticipantResult::fromDomain($participant);
    }
}
