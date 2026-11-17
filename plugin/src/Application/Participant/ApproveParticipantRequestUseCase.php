<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantConsentSource;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Join-request approval, R14-B §4.5 / §4.7 / §5.2 / §5.4:
 *
 * - One transaction, locks Production -> Participant; the manager's
 *   permission and the row's state are judged on the locked rows.
 * - Only a PENDING row: anything else (already approved / rejected, or
 *   taken over by an invitation acceptance) is 409
 *   stageart_participant_request_not_pending.
 * - Participant::approve() makes it ACTIVE. For an R14-B request
 *   (requestedAt set) the Person's consent is recorded as JOIN_REQUEST at
 *   the REQUEST time, not the approval time; a pre-R14-B request
 *   (requestedAt NULL) records no consent. enrollmentSource is unchanged.
 * - When the Person thereby becomes an ACTIVE participant, their ACTIVE
 *   delegate rows become INACTIVE (DORMANT_ON_ENROLLMENT).
 * - JOIN_APPROVED is recorded. The design defines no notification for an
 *   approval (§11.1), so none is sent.
 */
final class ApproveParticipantRequestUseCase
{
    private ParticipantRepositoryInterface $participants;
    private ProductionRepositoryInterface $productions;
    private PersonRepositoryInterface $people;
    private ProductionAuthorizationService $authorization;
    private ParticipantEnrollmentService $enrollment;
    private ParticipantDelegateRoleReleaser $delegateRoles;
    private ParticipantEventRecorder $events;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        PersonRepositoryInterface $people,
        ProductionAuthorizationService $authorization,
        ParticipantEnrollmentService $enrollment,
        ParticipantDelegateRoleReleaser $delegateRoles,
        ParticipantEventRecorder $events,
        TransactionManagerInterface $transactions
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
        $this->people = $people;
        $this->authorization = $authorization;
        $this->enrollment = $enrollment;
        $this->delegateRoles = $delegateRoles;
        $this->events = $events;
        $this->transactions = $transactions;
    }

    public function execute(ApproveParticipantRequestCommand $command): ParticipantRequestResult
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
                throw new ParticipantAccessDeniedException('Only a Production manager can approve this Participant request.');
            }

            $participant = $this->participants->findByIdForUpdate($participantId);

            if (! $participant) {
                throw new ParticipantNotFoundException($participantId->toString());
            }

            if (! $participant->isPending()) {
                throw new ParticipantRequestNotPendingException('This Participant request has already been handled.');
            }

            $personBecomesParticipant = $this->enrollment->personBecomesParticipantWith($participant);
            $participant->approve();
            $consentRecorded = false;

            if ($participant->requestedAt() !== null) {
                $participant->recordConsent(ParticipantConsentSource::joinRequest(), $participant->requestedAt());
                $consentRecorded = true;
            }

            $this->participants->save($participant);

            if ($personBecomesParticipant) {
                $this->delegateRoles->release(
                    $production->id(),
                    PersonId::fromString($participant->subjectId()),
                    $person->id(),
                    ParticipantDelegateRoleReleaser::DORMANT_ON_ENROLLMENT,
                    $participant,
                    $now
                );
            }

            $this->events->joinApproved($participant, $person->id(), $consentRecorded, $now);

            return $participant;
        });

        $requester = $this->people->findById(PersonId::fromString($participant->subjectId()));

        return ParticipantRequestResult::fromDomain($participant, $requester);
    }
}
