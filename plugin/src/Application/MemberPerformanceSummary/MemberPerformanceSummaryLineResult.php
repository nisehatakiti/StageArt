<?php

declare(strict_types=1);

namespace StageArt\Application\MemberPerformanceSummary;

final class MemberPerformanceSummaryLineResult
{
    public string $personId;
    public ?string $displayName;
    public int $attendedCount;
    public int $absentCount;
    public int $lateCount;
    public int $earlyLeftCount;
    public int $rehearsalCount;
    public int $ticketSalesCount;
    public int $ticketAttendanceCount;

    public function __construct(
        string $personId,
        ?string $displayName,
        int $attendedCount,
        int $absentCount,
        int $lateCount,
        int $earlyLeftCount,
        int $rehearsalCount,
        int $ticketSalesCount,
        int $ticketAttendanceCount
    ) {
        $this->personId = $personId;
        $this->displayName = $displayName;
        $this->attendedCount = $attendedCount;
        $this->absentCount = $absentCount;
        $this->lateCount = $lateCount;
        $this->earlyLeftCount = $earlyLeftCount;
        $this->rehearsalCount = $rehearsalCount;
        $this->ticketSalesCount = $ticketSalesCount;
        $this->ticketAttendanceCount = $ticketAttendanceCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'person_id' => $this->personId,
            'display_name' => $this->displayName,
            'attended_count' => $this->attendedCount,
            'absent_count' => $this->absentCount,
            'late_count' => $this->lateCount,
            'early_left_count' => $this->earlyLeftCount,
            'rehearsal_count' => $this->rehearsalCount,
            'ticket_sales_count' => $this->ticketSalesCount,
            'ticket_attendance_count' => $this->ticketAttendanceCount,
        ];
    }
}
