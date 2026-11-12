<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

final class PerformanceTicketAvailabilityResult
{
    public string $performanceId;
    public string $performanceDate;
    public string $startTime;
    public string $performanceStatus;
    public bool $isTicketPublished;
    public ?string $salesStartAt;
    public ?string $salesEndAt;
    public bool $isSalesOpen;

    public function __construct(
        string $performanceId,
        string $performanceDate,
        string $startTime,
        string $performanceStatus,
        bool $isTicketPublished,
        ?string $salesStartAt,
        ?string $salesEndAt,
        bool $isSalesOpen
    ) {
        $this->performanceId = $performanceId;
        $this->performanceDate = $performanceDate;
        $this->startTime = $startTime;
        $this->performanceStatus = $performanceStatus;
        $this->isTicketPublished = $isTicketPublished;
        $this->salesStartAt = $salesStartAt;
        $this->salesEndAt = $salesEndAt;
        $this->isSalesOpen = $isSalesOpen;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'performance_id' => $this->performanceId,
            'performance_date' => $this->performanceDate,
            'start_time' => $this->startTime,
            'performance_status' => $this->performanceStatus,
            'is_ticket_published' => $this->isTicketPublished,
            'sales_start_at' => $this->salesStartAt,
            'sales_end_at' => $this->salesEndAt,
            'is_sales_open' => $this->isSalesOpen,
        ];
    }
}
