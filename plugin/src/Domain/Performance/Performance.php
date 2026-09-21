<?php

declare(strict_types=1);

namespace StageArt\Domain\Performance;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Production\ProductionId;

/**
 * Phase 2 Performance基盤 instruction: Performance is Production's child
 * Entity ("1つのProductionには複数のPerformanceを作成できる"), with a Status
 * lifecycle independent of Production's own Lifecycle (§8 - "Production
 * LifecycleとPerformance Statusは独立して管理する"). No individual venue
 * field (§13 - venue is always the parent Production's); no per-instance
 * timezone (matching PerformanceConsistencyPolicy.md's "Performanceごとに
 * 別Timezoneを設定することを基本としない" - `performanceDate` mirrors
 * Production's own `scheduleStartDate` DATE-only convention with no
 * DateTimeZone handling, and `startTime`/`endTime` are plain wall-clock
 * "H:i:s" strings rather than DateTimeImmutable, so there is no instant
 * to mis-timezone in the first place - both readings of the instruction's
 * "follow existing Production and Rehearsal conventions" resolve to the
 * same design without borrowing Rehearsal's per-row timezone column,
 * which the Blueprint explicitly does not want for Performance).
 *
 * `capacity` starts as a copy of the parent Production's own capacity at
 * creation time (§10) but is independently mutable per Performance
 * thereafter (§10) - except for the mandatory, unconditional overwrite a
 * Production capacity change cascades onto every child Performance
 * (§11/§12), which is orchestrated by the Application layer via
 * `changeCapacity()` below, deliberately bypassing `updateBasicInfo()`'s
 * CANCELLED guard (§26 requires the cascade to overwrite CANCELLED
 * Performances too).
 */
final class Performance
{
    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    private PerformanceId $id;
    private ProductionId $productionId;
    private DateTimeImmutable $performanceDate;
    private string $startTime;
    private ?string $endTime;
    private int $capacity;
    private ?string $remarks;
    private ?string $symbol;
    private PerformanceStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        PerformanceId $id,
        ProductionId $productionId,
        DateTimeImmutable $performanceDate,
        string $startTime,
        ?string $endTime,
        int $capacity,
        ?string $remarks,
        ?string $symbol,
        PerformanceStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->performanceDate = $performanceDate;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->capacity = $capacity;
        $this->remarks = $remarks;
        $this->symbol = $symbol;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        ProductionId $productionId,
        DateTimeImmutable $performanceDate,
        string $startTime,
        ?string $endTime,
        int $capacity,
        ?string $remarks,
        ?string $symbol
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            PerformanceId::generate(),
            $productionId,
            $performanceDate,
            self::normalizeTime($startTime),
            self::normalizeOptionalTime($endTime),
            self::validateCapacity($capacity),
            self::normalizeNullableString($remarks),
            self::normalizeNullableString($symbol),
            PerformanceStatus::published(),
            $now,
            $now
        );
    }

    public static function reconstitute(
        PerformanceId $id,
        ProductionId $productionId,
        DateTimeImmutable $performanceDate,
        string $startTime,
        ?string $endTime,
        int $capacity,
        ?string $remarks,
        ?string $symbol,
        PerformanceStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $productionId,
            $performanceDate,
            $startTime,
            $endTime,
            $capacity,
            $remarks,
            $symbol,
            $status,
            $createdAt,
            $updatedAt
        );
    }

    /**
     * §15 "編集": 公演日/開演時刻/終演予定時刻/定員/備考/記号. Status is
     * changed separately via `changeStatus()`/`cancel()`, matching
     * Rehearsal::updateBasicInfo()'s precedent of never touching Status.
     * Rejected once CANCELLED (a cancelled Performance's schedule/
     * capacity no longer makes sense to edit); the mandatory Production
     * capacity cascade uses `changeCapacity()` instead specifically to
     * bypass this guard.
     */
    public function updateBasicInfo(
        DateTimeImmutable $performanceDate,
        string $startTime,
        ?string $endTime,
        int $capacity,
        ?string $remarks,
        ?string $symbol
    ): void {
        if ($this->status->equals(PerformanceStatus::fromString(PerformanceStatus::CANCELLED))) {
            throw new InvalidArgumentException('A CANCELLED Performance cannot be edited.');
        }

        $this->performanceDate = $performanceDate;
        $this->startTime = self::normalizeTime($startTime);
        $this->endTime = self::normalizeOptionalTime($endTime);
        $this->capacity = self::validateCapacity($capacity);
        $this->remarks = self::normalizeNullableString($remarks);
        $this->symbol = self::normalizeNullableString($symbol);
        $this->touch();
    }

    /**
     * §11/§12/§26: the one path the mandatory Production-capacity cascade
     * uses - unconditional, including for an already-CANCELLED
     * Performance ("個別に変更されていたPerformanceも例外ではありません" /
     * "CANCELLED Performanceについても...原則として対象に含める").
     */
    public function changeCapacity(int $capacity): void
    {
        $this->capacity = self::validateCapacity($capacity);
        $this->touch();
    }

    /**
     * §15 "Status（権限・業務ルールに応じた変更）": no strict transition
     * graph is mandated among PUBLISHED/SOLD_OUT/FINISHED - only
     * that CANCELLED is terminal (§27 flags "Performance Statusの追加・
     * 削除" as a judgment-pending case, not the transitions among the
     * five confirmed values). `cancel()` is the dedicated, idempotency-
     * guarded path into CANCELLED specifically (§7 "物理削除ではなく
     * CANCELLEDへの状態変更として扱う").
     */
    public function changeStatus(PerformanceStatus $status): void
    {
        if ($this->status->equals(PerformanceStatus::fromString(PerformanceStatus::CANCELLED))) {
            throw new InvalidArgumentException('A CANCELLED Performance\'s Status cannot be changed.');
        }

        $this->status = $status;
        $this->touch();
    }

    public function cancel(): void
    {
        if ($this->status->equals(PerformanceStatus::fromString(PerformanceStatus::CANCELLED))) {
            throw new InvalidArgumentException('Performance is already CANCELLED.');
        }

        $this->status = PerformanceStatus::fromString(PerformanceStatus::CANCELLED);
        $this->touch();
    }

    private static function validateCapacity(int $capacity): int
    {
        if ($capacity < 1) {
            throw new InvalidArgumentException('Performance capacity must be a positive integer.');
        }

        return $capacity;
    }

    private static function normalizeTime(string $time): string
    {
        $trimmed = trim($time);

        if (! preg_match(self::TIME_PATTERN, $trimmed)) {
            throw new InvalidArgumentException("Invalid time value (expected H:i or H:i:s): {$time}");
        }

        return strlen($trimmed) === 5 ? "{$trimmed}:00" : $trimmed;
    }

    private static function normalizeOptionalTime(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        return self::normalizeTime($time);
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): PerformanceId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function performanceDate(): DateTimeImmutable
    {
        return $this->performanceDate;
    }

    public function startTime(): string
    {
        return $this->startTime;
    }

    /**
     * Phase 3 Ticket/Reservation基盤: a pure derived getter (no new
     * field, no schema impact) combining `performanceDate` + `startTime`
     * into a single instant - needed by the Ticket Module's
     * `Domain\Ticket\SalesEndRule::computeDeadline()` (§8's "開演の指定
     * 時間前まで" rule) and by Reservation modification Validation (§29's
     * "開演後" cutoff), without requiring the Ticket Module to duplicate
     * Performance's own date+time composition logic.
     */
    public function startDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->performanceDate->format('Y-m-d') . ' ' . $this->startTime);
    }

    public function endTime(): ?string
    {
        return $this->endTime;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function remarks(): ?string
    {
        return $this->remarks;
    }

    public function symbol(): ?string
    {
        return $this->symbol;
    }

    public function status(): PerformanceStatus
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
