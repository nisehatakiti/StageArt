<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class ApproveParticipantRequestUseCase
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

    public function execute(ApproveParticipantRequestCommand $command): ParticipantRequestResult
    {
        $participant = $this->participants->findById(ParticipantId::fromString($command->participantId));

        if (! $participant) {
            throw new ParticipantNotFoundException($command->participantId);
        }

        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);
        $production = $this->productions->findById($participant->productionId());

        if (! $person || ! $production || ! $this->authorization->canManageParticipants($person, $production)) {
            throw new ParticipantAccessDeniedException('Only a Production manager can approve this Participant request.');
        }

        $participant->approve();
        $this->participants->save($participant);

        $requester = $this->people->findById(PersonId::fromString($participant->subjectId()));

        return ParticipantRequestResult::fromDomain($participant, $requester);
    }
}
