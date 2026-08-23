<?php

declare(strict_types=1);

namespace StageArt\Domain\Rehearsal;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Production\ProductionId;

/**
 * Title/Description/Location/Timezone/Start/End are all kept as plain
 * nullable scalars rather than Value Objects: Rehearsal.md phrases every
 * one of them as "設定できる" (optional), and Production.md's "Creation"
 * section explicitly allows a Rehearsal to exist before its schedule is
 * finalized ("まだ日程が確定していない場合がある"). This mirrors
 * Project.name's precedent from Phase 1 for the same reason.
 *
 * create() always starts at SCHEDULED (not DRAFT): BusinessFlow.md
 * Flow 18 ("稽古予定を提示する(日程調整開始)") describes creation and
 * beginning the schedule-adjustment period as a single Business
 * Operation, and Phase 2 instruction §5 requires Phase 1 Attendance
 * generation to happen at creation time - which only makes sense once
 * target members exist to adjust their schedule against. A DRAFT-only
 * creation path with a later separate "publish" step is not implemented
 * this phase (see the Phase 2 report's 未実装 section).
 */
final class Rehearsal
{
    private RehearsalId $id;
    private ProductionId $productionId;
    private ?string $title;
    private ?string $description;
    private ?DateTimeImmutable $startDateTime;
    private ?DateTimeImmutable $endDateTime;
    private ?string $timezone;
    private ?string $location;
    private RehearsalStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        RehearsalId $id,
        ProductionId $productionId,
        ?string $title,
        ?string $description,
        ?DateTimeImmutable $startDateTime,
        ?DateTimeImmutable $endDateTime,
        ?string $timezone,
        ?string $location,
        RehearsalStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->title = $title;
        $this->description = $description;
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
        $this->timezone = $timezone;
        $this->location = $location;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        ProductionId $productionId,
        ?string $title,
        ?string $description,
        ?DateTimeImmutable $startDateTime,
        ?DateTimeImmutable $endDateTime,
        ?string $timezone,
        ?string $location
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            RehearsalId::generate(),
            $productionId,
            $title,
            $description,
            $startDateTime,
            $endDateTime,
            $timezone,
            $location,
            RehearsalStatus::scheduled(),
            $now,
            $now
        );
    }

    public static function reconstitute(
        RehearsalId $id,
        ProductionId $productionId,
        ?string $title,
        ?string $description,
        ?DateTimeImmutable $startDateTime,
        ?DateTimeImmutable $endDateTime,
        ?string $timezone,
        ?string $location,
        RehearsalStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $productionId,
            $title,
            $description,
            $startDateTime,
            $endDateTime,
            $timezone,
            $location,
            $status,
            $createdAt,
            $updatedAt
        );
    }

    public function updateBasicInfo(
        ?string $title,
        ?string $description,
        ?DateTimeImmutable $startDateTime,
        ?DateTimeImmutable $endDateTime,
        ?string $timezone,
        ?string $location
    ): void {
        if ($this->isTerminal()) {
            throw new InvalidArgumentException(
                'A COMPLETED or CANCELLED Rehearsal\'s basic information cannot be edited.'
            );
        }

        $this->title = $title;
        $this->description = $description;
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
        $this->timezone = $timezone;
        $this->location = $location;
        $this->touch();
    }

    /**
     * The one transition with a major side effect (Phase 2 Attendance
     * generation) - the Application layer orchestrates that side effect,
     * but the Domain still enforces that only a SCHEDULED Rehearsal may
     * be confirmed, matching RehearsalAttendance.md's "Rehearsal
     * Confirmation" section and giving idempotency for free: calling
     * this twice on an already-CONFIRMED Rehearsal fails here before any
     * Phase 2 records could be duplicated.
     */
    public function confirm(): void
    {
        if (! $this->status->equals(RehearsalStatus::fromString(RehearsalStatus::SCHEDULED))) {
            throw new InvalidArgumentException('Only a SCHEDULED Rehearsal can be confirmed.');
        }

        $this->status = RehearsalStatus::fromString(RehearsalStatus::CONFIRMED);
        $this->touch();
    }

    public function activate(): void
    {
        if (! $this->status->equals(RehearsalStatus::fromString(RehearsalStatus::CONFIRMED))) {
            throw new InvalidArgumentException('Only a CONFIRMED Rehearsal can become ACTIVE.');
        }

        $this->status = RehearsalStatus::fromString(RehearsalStatus::ACTIVE);
        $this->touch();
    }

    public function complete(): void
    {
        if (! $this->status->equals(RehearsalStatus::fromString(RehearsalStatus::ACTIVE))) {
            throw new InvalidArgumentException('Only an ACTIVE Rehearsal can be completed.');
        }

        $this->status = RehearsalStatus::fromString(RehearsalStatus::COMPLETED);
        $this->touch();
    }

    public function cancel(): void
    {
        if ($this->isTerminal()) {
            throw new InvalidArgumentException('A COMPLETED or CANCELLED Rehearsal cannot be cancelled.');
        }

        $this->status = RehearsalStatus::fromString(RehearsalStatus::CANCELLED);
        $this->touch();
    }

    private function isTerminal(): bool
    {
        return $this->status->equals(RehearsalStatus::fromString(RehearsalStatus::COMPLETED))
            || $this->status->equals(RehearsalStatus::fromString(RehearsalStatus::CANCELLED));
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): RehearsalId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function startDateTime(): ?DateTimeImmutable
    {
        return $this->startDateTime;
    }

    public function endDateTime(): ?DateTimeImmutable
    {
        return $this->endDateTime;
    }

    public function timezone(): ?string
    {
        return $this->timezone;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function status(): RehearsalStatus
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
