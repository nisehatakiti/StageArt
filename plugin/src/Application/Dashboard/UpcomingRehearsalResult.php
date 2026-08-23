<?php

declare(strict_types=1);

namespace StageArt\Application\Dashboard;

use StageArt\Domain\Production\Production;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;

/**
 * A Dashboard-specific projection of Rehearsal + RehearsalAttendance +
 * Production - not a Domain Entity, not a re-export of RehearsalResult
 * (which has no per-Person Attendance status). Carries no UI concept
 * (no "card"/"section"/"color") per this Phase's §18: only the
 * identifying and scheduling data DashboardPolicy.md's Display list
 * ("稽古日, 開始/終了時刻, 稽古場所, Production名") requires, plus the
 * caller's own attendanceStatus (useful context, already public per
 * RehearsalAttendanceResult's existing precedent).
 */
final class UpcomingRehearsalResult
{
    public string $rehearsalId;
    public string $productionId;
    public string $productionName;
    public ?string $title;
    public ?string $startDateTime;
    public ?string $endDateTime;
    public ?string $location;
    public string $attendanceStatus;

    private function __construct(
        string $rehearsalId,
        string $productionId,
        string $productionName,
        ?string $title,
        ?string $startDateTime,
        ?string $endDateTime,
        ?string $location,
        string $attendanceStatus
    ) {
        $this->rehearsalId = $rehearsalId;
        $this->productionId = $productionId;
        $this->productionName = $productionName;
        $this->title = $title;
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
        $this->location = $location;
        $this->attendanceStatus = $attendanceStatus;
    }

    public static function fromDomain(RehearsalAttendance $attendance, Rehearsal $rehearsal, Production $production): self
    {
        return new self(
            $rehearsal->id()->toString(),
            $production->id()->toString(),
            $production->name()->toString(),
            $rehearsal->title(),
            $rehearsal->startDateTime() !== null ? $rehearsal->startDateTime()->format(DATE_ATOM) : null,
            $rehearsal->endDateTime() !== null ? $rehearsal->endDateTime()->format(DATE_ATOM) : null,
            $rehearsal->location(),
            $attendance->status()->toString()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rehearsal_id' => $this->rehearsalId,
            'production_id' => $this->productionId,
            'production_name' => $this->productionName,
            'title' => $this->title,
            'start_date_time' => $this->startDateTime,
            'end_date_time' => $this->endDateTime,
            'location' => $this->location,
            'attendance_status' => $this->attendanceStatus,
        ];
    }
}
