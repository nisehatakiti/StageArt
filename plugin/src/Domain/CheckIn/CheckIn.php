<?php

declare(strict_types=1);

namespace StageArt\Domain\CheckIn;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Reservation\ReservationId;

/**
 * CheckIn.md/CheckInConsistencyPolicy.md: Check In is a Fact distinct
 * from Reservation ("Reservationは予約Fact...Check Inは来場受付Fact") and
 * must be kept as its own persisted record, not folded into
 * Reservation's own updatedBy/updatedAt (which a later, unrelated update
 * to the same Reservation would silently overwrite, destroying the
 * "Checked In By / Checked In At" audit trail CheckIn.md's "# Audit"
 * section requires to survive independently of Reservation's own
 * lifecycle - including a later Check-in Reversal, which still must not
 * erase that this Reservation was once genuinely checked in and by whom).
 *
 * One CheckIn row exists per completed Check-in event; reversing does
 * not delete it (CheckIn.md "# Reversed": "物理削除は行わない") - it
 * transitions COMPLETED -> REVERSED in place, recording who reversed it
 * and when, alongside (not replacing) the original Checked In By/At.
 */
final class CheckIn
{
    private CheckInId $id;
    private ReservationId $reservationId;
    private PerformanceId $performanceId;
    private CheckInStatus $status;
    private PersonId $checkedInBy;
    private DateTimeImmutable $checkedInAt;
    private ?PersonId $reversedBy;
    private ?DateTimeImmutable $reversedAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        CheckInId $id,
        ReservationId $reservationId,
        PerformanceId $performanceId,
        CheckInStatus $status,
        PersonId $checkedInBy,
        DateTimeImmutable $checkedInAt,
        ?PersonId $reversedBy,
        ?DateTimeImmutable $reversedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->reservationId = $reservationId;
        $this->performanceId = $performanceId;
        $this->status = $status;
        $this->checkedInBy = $checkedInBy;
        $this->checkedInAt = $checkedInAt;
        $this->reversedBy = $reversedBy;
        $this->reversedAt = $reversedAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function complete(ReservationId $reservationId, PerformanceId $performanceId, PersonId $checkedInBy): self
    {
        $now = new DateTimeImmutable();

        return new self(
            CheckInId::generate(),
            $reservationId,
            $performanceId,
            CheckInStatus::completed(),
            $checkedInBy,
            $now,
            null,
            null,
            $now,
            $now
        );
    }

    public static function reconstitute(
        CheckInId $id,
        ReservationId $reservationId,
        PerformanceId $performanceId,
        CheckInStatus $status,
        PersonId $checkedInBy,
        DateTimeImmutable $checkedInAt,
        ?PersonId $reversedBy,
        ?DateTimeImmutable $reversedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $reservationId,
            $performanceId,
            $status,
            $checkedInBy,
            $checkedInAt,
            $reversedBy,
            $reversedAt,
            $createdAt,
            $updatedAt
        );
    }

    public function reverse(PersonId $reversedBy): void
    {
        if (! $this->status->equals(CheckInStatus::completed())) {
            throw new InvalidArgumentException('Only a COMPLETED Check-in can be reversed.');
        }

        $this->status = CheckInStatus::fromString(CheckInStatus::REVERSED);
        $this->reversedBy = $reversedBy;
        $this->reversedAt = new DateTimeImmutable();
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): CheckInId
    {
        return $this->id;
    }

    public function reservationId(): ReservationId
    {
        return $this->reservationId;
    }

    public function performanceId(): PerformanceId
    {
        return $this->performanceId;
    }

    public function status(): CheckInStatus
    {
        return $this->status;
    }

    public function isCompleted(): bool
    {
        return $this->status->equals(CheckInStatus::completed());
    }

    public function checkedInBy(): PersonId
    {
        return $this->checkedInBy;
    }

    public function checkedInAt(): DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function reversedBy(): ?PersonId
    {
        return $this->reversedBy;
    }

    public function reversedAt(): ?DateTimeImmutable
    {
        return $this->reversedAt;
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
