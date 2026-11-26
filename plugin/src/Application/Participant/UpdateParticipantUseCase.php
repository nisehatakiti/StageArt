<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\Shared\UniqueConstraintViolationException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * `PUT /participants/{id}` (participant_type, status, remarks).
 *
 * PR #7 R14-B stage 4 (design doc §5.2 / §7.1, N6-N9):
 * - Input (422 `stageart_participant_invalid`, nothing changed):
 *   participant_type is required and must be a valid type; status, when
 *   sent, must be a valid status string (null / "" / unknown / non-string
 *   refused); remarks, when sent, must be a string or null.
 * - status: not sent -> unchanged. The same value as now -> idempotent
 *   success (no transition, no audit, no delegate change). ACTIVE ->
 *   INACTIVE is the only change allowed: the Person's delegate rows in
 *   this Production all become INACTIVE in the same transaction
 *   (PARTICIPANT_DEACTIVATED) and DEACTIVATED is audited. INACTIVE ->
 *   ACTIVE is 422 `stageart_participant_status_requires_consent`; any
 *   other change is 422 `stageart_participant_status_change_not_allowed`.
 * - participant_type: a different type for which the same subject already
 *   has a row in this Production (any status) is 409
 *   `stageart_participant_type_conflict`; a UNIQUE violation (1062) on
 *   save is turned into the same 409. Any other save failure propagates
 *   (500). An actual change is audited TYPE_CHANGED.
 * - remarks: not sent -> kept; null / "" -> NULL; a string -> trimmed.
 * - One transaction, locked Production -> Participant -> delegate (§5.4);
 *   any failure rolls the whole PUT back.
 */
final class UpdateParticipantUseCase
{
    private ParticipantRepositoryInterface $participants;
    private ProductionRepositoryInterface $productions;
    private PersonRepositoryInterface $people;
    private ProductionAuthorizationService $authorization;
    private PersonEmailResolver $personEmailResolver;
    private ParticipantDelegateRoleReleaser $releaser;
    private ParticipantEventRecorder $events;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        PersonRepositoryInterface $people,
        ProductionAuthorizationService $authorization,
        PersonEmailResolver $personEmailResolver,
        ParticipantDelegateRoleReleaser $releaser,
        ParticipantEventRecorder $events,
        TransactionManagerInterface $transactions
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
        $this->people = $people;
        $this->authorization = $authorization;
        $this->personEmailResolver = $personEmailResolver;
        $this->releaser = $releaser;
        $this->events = $events;
        $this->transactions = $transactions;
    }

    public function execute(UpdateParticipantCommand $command): ParticipantResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $participantId = ParticipantId::fromString($command->participantId);
        $participant = $this->participants->findById($participantId);

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

        [$type, $status, $remarks] = self::validated($command);

        $participant = $this->transactions->run(function () use ($production, $participantId, $type, $status, $remarks, $command, $requester): Participant {
            if ($this->productions->findByIdForUpdate($production->id()) === null) {
                throw new ProductionNotFoundException($production->id()->toString());
            }

            $locked = $this->participants->findByIdForUpdate($participantId);

            if ($locked === null) {
                throw new ParticipantNotFoundException($participantId->toString());
            }

            $deactivate = $status !== null && self::isDeactivation($locked->status()->toString(), $status);
            $fromType = $locked->participantType()->toString();
            $typeChanges = $fromType !== $type->toString();

            if ($typeChanges && $this->participants->findByProductionAndSubjectForUpdate(
                $locked->productionId(),
                $locked->subjectType(),
                $locked->subjectId(),
                $type
            ) !== null) {
                throw new ParticipantTypeConflictException('This member is already registered with that participant type in this production.');
            }

            $locked->changeParticipantType($type);

            if ($deactivate) {
                $locked->deactivate();
            }

            if ($command->sendsRemarks()) {
                $locked->changeRemarks($remarks);
            }

            try {
                $this->participants->save($locked);
            } catch (UniqueConstraintViolationException $exception) {
                throw new ParticipantTypeConflictException('This member is already registered with that participant type in this production.', 0, $exception);
            }

            $now = new DateTimeImmutable();

            if ($deactivate) {
                if ($locked->subjectType()->equals(ParticipantSubjectType::person())) {
                    $this->releaser->release(
                        $locked->productionId(),
                        PersonId::fromString($locked->subjectId()),
                        $requester->id(),
                        ParticipantDelegateRoleReleaser::PARTICIPANT_DEACTIVATED,
                        $locked,
                        $now
                    );
                }

                $this->events->deactivated($locked, $requester->id(), $now);
            }

            if ($typeChanges) {
                $this->events->typeChanged($locked, $fromType, $requester->id(), $now);
            }

            return $locked;
        });

        // R14-B stage 6 (§7.3): the response shows this manager what the
        // list would show of the row.
        $disclosure = ParticipantDisclosurePolicy::disclosureFor(
            $participant,
            $production,
            (string) ParticipantDisclosurePolicy::viewerOf($this->authorization, $requester, $production),
            $requester
        ) ?? ParticipantDisclosure::statusOnly();

        $isPersonSubject = $participant->subjectType()->equals(ParticipantSubjectType::person());
        $person = $isPersonSubject && $disclosure->names ? $this->people->findById(PersonId::fromString($participant->subjectId())) : null;
        $email = $isPersonSubject && $disclosure->email ? $this->personEmailResolver->resolve(PersonId::fromString($participant->subjectId())) : null;

        return ParticipantResult::disclosed($participant, $disclosure, $person, $email);
    }

    /**
     * @return array{0: ParticipantType, 1: ?string, 2: ?string} the type, the sent status (null = not sent), the remarks
     */
    private static function validated(UpdateParticipantCommand $command): array
    {
        if (! is_string($command->participantType) || trim($command->participantType) === '') {
            throw new InvalidArgumentException('participant_type is required.');
        }

        $type = ParticipantType::fromString($command->participantType);
        $status = null;

        if ($command->sendsStatus()) {
            if (! is_string($command->status) || $command->status === '') {
                throw new InvalidArgumentException('status must be a participant status.');
            }

            $status = ParticipantStatus::fromString($command->status)->toString();
        }

        $remarks = null;

        if ($command->sendsRemarks()) {
            if ($command->remarks !== null && ! is_string($command->remarks)) {
                throw new InvalidArgumentException('remarks must be a string or null.');
            }

            $remarks = $command->remarks;
        }

        return [$type, $status, $remarks];
    }

    /**
     * N6 / N9: true for ACTIVE -> INACTIVE; false for the same status
     * (idempotent, not a transition); every other change is refused.
     */
    private static function isDeactivation(string $current, string $target): bool
    {
        if ($current === $target) {
            return false;
        }

        if ($current === ParticipantStatus::ACTIVE && $target === ParticipantStatus::INACTIVE) {
            return true;
        }

        if ($current === ParticipantStatus::INACTIVE && $target === ParticipantStatus::ACTIVE) {
            throw new ParticipantStatusRequiresConsentException(
                'An INACTIVE member becomes ACTIVE again only by accepting an invitation or through an approved join request.'
            );
        }

        throw new ParticipantStatusChangeNotAllowedException("The status cannot be changed from {$current} to {$target}.");
    }
}
