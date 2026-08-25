<?php

declare(strict_types=1);

namespace StageArt\Domain\Participant;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Production\ProductionId;

/**
 * Creation defaults to ACTIVE rather than DRAFT: Participant.md defines
 * DRAFT as "参加がまだ確定していない状態" for a separate confirmation
 * step, but no such confirmation workflow exists in this phase (Phase 1
 * has no invitation/acceptance flow), so a Participant a manager adds
 * directly is effectively confirmed on creation. This is a disclosed
 * implementation judgment, not an explicit Blueprint default.
 */
final class Participant
{
    private ParticipantId $id;
    private ProductionId $productionId;
    private ParticipantSubjectType $subjectType;
    private string $subjectId;
    private ParticipantType $participantType;
    private ParticipantStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        ParticipantId $id,
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType,
        ParticipantStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->participantType = $participantType;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            ParticipantId::generate(),
            $productionId,
            $subjectType,
            $subjectId,
            $participantType,
            ParticipantStatus::active(),
            $now,
            $now
        );
    }

    public static function reconstitute(
        ParticipantId $id,
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType,
        ParticipantStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $productionId, $subjectType, $subjectId, $participantType, $status, $createdAt, $updatedAt);
    }

    /**
     * StageArt Web β版: a Person requesting to join a Production (via
     * Join Key or search - docs/04-DomainModel/JoinKey.md's "Production
     * Join Key... Participant Flow") starts PENDING, not ACTIVE - see
     * approve()/reject(). Mirrors Membership::requestMembership()'s same
     * request-then-approve shape at the Production Scope.
     */
    public static function requestParticipation(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            ParticipantId::generate(),
            $productionId,
            $subjectType,
            $subjectId,
            $participantType,
            ParticipantStatus::fromString(ParticipantStatus::PENDING),
            $now,
            $now
        );
    }

    public function isPending(): bool
    {
        return $this->status->toString() === ParticipantStatus::PENDING;
    }

    public function approve(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING Participant can be approved.');
        }

        $this->activate();
    }

    public function reject(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING Participant can be rejected.');
        }

        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::REJECTED));
    }

    public function changeParticipantType(ParticipantType $participantType): void
    {
        $this->participantType = $participantType;
        $this->touch();
    }

    public function changeStatus(ParticipantStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function activate(): void
    {
        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::ACTIVE));
    }

    public function deactivate(): void
    {
        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::INACTIVE));
    }

    /**
     * Per Participant.md: "Participantは原則として物理削除しない" - the
     * Application layer's "delete" operation calls this instead of
     * removing the row.
     */
    public function cancel(): void
    {
        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::CANCELLED));
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ParticipantId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function subjectType(): ParticipantSubjectType
    {
        return $this->subjectType;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function participantType(): ParticipantType
    {
        return $this->participantType;
    }

    public function status(): ParticipantStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
