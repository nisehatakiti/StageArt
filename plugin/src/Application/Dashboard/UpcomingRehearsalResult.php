<?php

declare(strict_types=1);

namespace StageArt\Application\Dashboard;

/**
 * A Dashboard-specific projection of a Person's upcoming Rehearsal
 * attendance - not a Domain Entity, not a re-export of RehearsalResult
 * (which has no per-Person Attendance status). Carries no UI concept
 * (no "card"/"section"/"color") per this Phase's §18: only the
 * identifying and scheduling data DashboardPolicy.md's Display list
 * ("稽古日, 開始/終了時刻, 稽古場所, Production名") requires, plus the
 * caller's own attendanceStatus (useful context, already public per
 * RehearsalAttendanceResult's existing precedent).
 *
 * StageArt Core/Module Architecture Phase 4 §1: deliberately built from
 * primitives only (`create()`), not from `Rehearsal`/`RehearsalAttendance`
 * Domain Entities - Core's own `Application\Dashboard` namespace must
 * not import Rehearsal's Domain classes even to build this DTO.
 * `RehearsalUpcomingRehearsalProvider` (which does have legitimate
 * access to those Entities) is the only caller of `create()`.
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

    public static function create(
        string $rehearsalId,
        string $productionId,
        string $productionName,
        ?string $title,
        ?string $startDateTime,
        ?string $endDateTime,
        ?string $location,
        string $attendanceStatus
    ): self {
        return new self(
            $rehearsalId,
            $productionId,
            $productionName,
            $title,
            $startDateTime,
            $endDateTime,
            $location,
            $attendanceStatus
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
