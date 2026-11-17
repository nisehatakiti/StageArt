<?php

declare(strict_types=1);

namespace StageArt\Domain\Participant;

use StageArt\Domain\Production\ProductionId;

interface ParticipantRepositoryInterface
{
    public function save(Participant $participant): void;

    public function findById(ParticipantId $id): ?Participant;

    /**
     * R14-B §5.4: findById() with an exclusive row lock held until the
     * caller's transaction ends (SELECT ... FOR UPDATE), taken after the
     * Production lock. Throws when the read fails; null only when there is
     * no such row.
     */
    public function findByIdForUpdate(ParticipantId $id): ?Participant;

    /**
     * R14-B §5.2 / §7.4 (U13): the one row of this (production, subject,
     * type) key, locked (SELECT ... FOR UPDATE) - read again under the
     * Production lock before a row is created or reused, so concurrent
     * requests / acceptances never create a second row. Throws when the
     * read fails; null only when there is no such row.
     */
    public function findByProductionAndSubjectForUpdate(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): ?Participant;

    /**
     * R14-B §4.6 (DORMANT_ON_ENROLLMENT): whether the Person holds an ACTIVE
     * PERSON row of a type other than $participantType in the Production.
     * The rows are read with SELECT ... FOR UPDATE (after the Production
     * lock, §5.4). Rows of $participantType itself and non-PERSON rows
     * (NAME_ONLY) never count. Throws when the read fails - a failure is
     * never taken for "none" (which would release delegate roles that must
     * be kept).
     */
    public function existsActivePersonParticipantOfAnotherTypeForUpdate(
        ProductionId $productionId,
        string $personId,
        ParticipantType $participantType
    ): bool;

    /**
     * R14-B §4.6 / §7.4 (stage 4): whether the Person has an ACTIVE PERSON
     * row (any type) with recorded consent (consented_at IS NOT NULL) in
     * the Production - the participant half of an effective
     * ProductionDelegate. NAME_ONLY / ORGANIZATION rows never count, and
     * created_at is never taken for consent. Throws when the read fails
     * (never answers "no" for a failure).
     */
    public function existsConsentedActivePersonParticipant(ProductionId $productionId, string $personId): bool;

    /**
     * R14-B §4.6 (stage 4): the Person's PERSON rows (every type and
     * status) in the Production - to tell NOT_ACTIVE_PARTICIPANT from
     * CONSENT_UNCONFIRMED. Throws when the read fails.
     *
     * @return Participant[]
     */
    public function findPersonParticipants(ProductionId $productionId, string $personId): array;

    /**
     * findPersonParticipants() with SELECT ... FOR UPDATE (delegate
     * appointment: Production -> Participant -> delegate, §5.4). Throws when
     * the read fails.
     *
     * @return Participant[]
     */
    public function findPersonParticipantsForUpdate(ProductionId $productionId, string $personId): array;

    /**
     * @return Participant[]
     */
    public function findByProductionId(ProductionId $productionId): array;

    public function findByProductionAndSubject(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): ?Participant;

    /**
     * Phase 7.3: the Person-first counterpart to findByProductionId(),
     * needed to resolve "every Production this subject participates in"
     * without an N+1 scan over all Productions. Every ParticipantType
     * and Status is returned; callers filter (e.g. to ACTIVE) themselves,
     * matching this Repository's existing "no business rules here" split.
     *
     * @return Participant[]
     */
    public function findBySubject(ParticipantSubjectType $subjectType, string $subjectId): array;
}
