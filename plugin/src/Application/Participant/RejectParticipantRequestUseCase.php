<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Join-request rejection, R14-B §4.7 / §5.2 / §5.4: one transaction, locks
 * Production -> Participant, judged on the locked row - only PENDING
 * (otherwise 409 stageart_participant_request_not_pending, e.g. when an
 * invitation acceptance took the request over first). Participant::reject()
 * makes it REJECTED and JOIN_REJECTED is recorded. The design defines no
 * notification for it (§11.1), so none is sent.
 */
final class RejectParticipantRequestUseCase
{
    private ParticipantRepositoryInterface $participants;
    private ProductionRepositoryInterface $productions;
    private PersonRepositoryInterface $people;
    private ProductionAuthorizationService $authorization;
    private ParticipantEventRecorder $events;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        PersonRepositoryInterface $people,
        ProductionAuthorizationService $authorization,
        ParticipantEventRecorder $events,
        TransactionManagerInterface $transactions
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
        $this->people = $people;
        $this->authorization = $authorization;
        $this->events = $events;
        $this->transactions = $transactions;
    }

    public function execute(RejectParticipantRequestCommand $command): ParticipantRequestResult
    {
        $participantId = ParticipantId::fromString($command->participantId);
        $unlocked = $this->participants->findById($participantId);

        if (! $unlocked) {
            throw new ParticipantNotFoundException($command->participantId);
        }

        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);
        $now = new DateTimeImmutable();

        $participant = $this->transactions->run(function () use ($unlocked, $participantId, $person, $now): Participant {
            $production = $this->productions->findByIdForUpdate($unlocked->productionId());

            if (! $person || ! $production || ! $this->authorization->canManageParticipants($person, $production)) {
                throw new ParticipantAccessDeniedException('Only a Production manager can reject this Participant request.');
            }

            $participant = $this->participants->findByIdForUpdate($participantId);

            if (! $participant) {
                throw new ParticipantNotFoundException($participantId->toString());
            }

            if (! $participant->isPending()) {
                throw new ParticipantRequestNotPendingException('This Participant request has already been handled.');
            }

            $participant->reject();
            $this->participants->save($participant);
            $this->events->joinRejected($participant, $person->id(), $now);

            return $participant;
        });

        $requester = $this->people->findById(PersonId::fromString($participant->subjectId()));

        return ParticipantRequestResult::fromDomain($participant, $requester);
    }
}
