<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use DateTimeImmutable;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B §5.2 / §7.4 (U13 A案, N1, N11): the one place where an invitation
 * (automatic registration, invitation policy 2026-11-27) and a join request
 * create or reuse a PERSON Participant row.
 * The (production_id, subject_type, subject_id, participant_type) key has
 * exactly one row, whatever its status: it is read again with SELECT ...
 * FOR UPDATE and, when it exists, its status is changed - a new
 * Participant id is never issued for the same key.
 *
 * The caller has started the transaction and locked the Production first
 * (§5.4); this only takes the Participant row lock. The saved row is
 * checked by the Repository (a failed or duplicate write throws, so the
 * caller never reports success without the row).
 */
final class ParticipantEnrollmentService
{
    private ParticipantRepositoryInterface $participants;

    public function __construct(ParticipantRepositoryInterface $participants)
    {
        $this->participants = $participants;
    }

    /**
     * Registration from an invitation (invitation policy 2026-11-27: no
     * acceptance by the person, and no consent is recorded):
     * - no row: a new ACTIVE row (enrollmentSource = INVITATION, no consent);
     * - CANCELLED / WITHDRAWN / REJECTED / INACTIVE / PENDING: the same row
     *   becomes ACTIVE; enrollmentSource and any earlier consent are kept as
     *   they are;
     * - ACTIVE: nothing changes (previousStatus ACTIVE, becameActive false) -
     *   the caller decides (409 when inviting, already done when resolving);
     * - DRAFT (of this type): ParticipantDraftExistsException, nothing
     *   changed (a DRAFT row of another type is a different key and never
     *   looked at).
     *
     * @throws ParticipantDraftExistsException
     */
    public function enrollByInvitation(
        ProductionId $productionId,
        PersonId $personId,
        ParticipantType $participantType,
        ?string $displayName,
        ?string $remarks
    ): ParticipantEnrollmentOutcome {
        $existing = $this->lockRow($productionId, $personId, $participantType);

        if ($existing === null) {
            $wasParticipant = $this->isActiveInAnotherType($productionId, $personId, $participantType);
            $participant = Participant::enrollByInvitation(
                $productionId,
                $personId->toString(),
                $participantType,
                $remarks,
                $displayName
            );
            $this->participants->save($participant);

            return new ParticipantEnrollmentOutcome($participant, null, true, ! $wasParticipant);
        }

        $previous = $existing->status()->toString();

        if ($previous === ParticipantStatus::DRAFT) {
            throw new ParticipantDraftExistsException('A DRAFT Participant of this type already exists for the invitee.');
        }

        if ($previous === ParticipantStatus::ACTIVE) {
            return new ParticipantEnrollmentOutcome($existing, $previous, false, false);
        }

        $personBecameParticipant = ! $this->isActiveInAnotherType($productionId, $personId, $participantType);
        $existing->reenroll();
        $this->participants->save($existing);

        return new ParticipantEnrollmentOutcome($existing, $previous, true, $personBecameParticipant);
    }

    /**
     * For join-request approval (§4.6): whether making this (non-ACTIVE)
     * row ACTIVE makes the Person an ACTIVE participant of the Production -
     * i.e. they hold no ACTIVE row of the other type there. Ask before
     * activating it; the caller has locked the Production.
     */
    public function personBecomesParticipantWith(Participant $participant): bool
    {
        return ! $this->isActiveInAnotherType(
            $participant->productionId(),
            PersonId::fromString($participant->subjectId()),
            $participant->participantType()
        );
    }

    /**
     * Whether the Person already holds an ACTIVE PERSON row of another type
     * in the Production. All Participant state changes of a Production run
     * under its row lock (§5.4), and the rows are read with FOR UPDATE, so
     * this read is stable for the transaction. A failed read throws (the
     * Repository never answers "none" for it), so the caller's transaction
     * rolls back instead of releasing delegate roles that must be kept.
     */
    private function isActiveInAnotherType(ProductionId $productionId, PersonId $personId, ParticipantType $participantType): bool
    {
        return $this->participants->existsActivePersonParticipantOfAnotherTypeForUpdate(
            $productionId,
            $personId->toString(),
            $participantType
        );
    }

    /**
     * Join request (§5.2, N1):
     * - no row: a new PENDING row (enrollmentSource = JOIN_REQUEST,
     *   requestedAt = this request);
     * - CANCELLED / REJECTED / WITHDRAWN / INACTIVE: the same row becomes
     *   PENDING again (reopenRequest(): id, enrollmentSource and consent
     *   kept, requestedAt = this request);
     * - ACTIVE / PENDING / DRAFT: ParticipantAlreadyExistsException (409),
     *   nothing changed.
     *
     * @throws ParticipantAlreadyExistsException
     */
    public function requestParticipation(
        ProductionId $productionId,
        PersonId $personId,
        ParticipantType $participantType,
        DateTimeImmutable $requestedAt
    ): ParticipantEnrollmentOutcome {
        $existing = $this->lockRow($productionId, $personId, $participantType);

        if ($existing === null) {
            $participant = Participant::requestParticipation(
                $productionId,
                ParticipantSubjectType::person(),
                $personId->toString(),
                $participantType,
                $requestedAt
            );
            $this->participants->save($participant);

            return new ParticipantEnrollmentOutcome($participant, null, false);
        }

        $previous = $existing->status()->toString();

        if (in_array($previous, [ParticipantStatus::ACTIVE, ParticipantStatus::PENDING, ParticipantStatus::DRAFT], true)) {
            throw new ParticipantAlreadyExistsException(
                "A {$previous} Participant of this type already exists for this Person in Production {$productionId->toString()}."
            );
        }

        $existing->reopenRequest($requestedAt);
        $this->participants->save($existing);

        return new ParticipantEnrollmentOutcome($existing, $previous, false);
    }

    private function lockRow(ProductionId $productionId, PersonId $personId, ParticipantType $participantType): ?Participant
    {
        return $this->participants->findByProductionAndSubjectForUpdate(
            $productionId,
            ParticipantSubjectType::person(),
            $personId->toString(),
            $participantType
        );
    }
}
