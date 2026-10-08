<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use StageArt\Domain\Performance\Performance;

/**
 * Phase 3 Ticket/Reservation基盤 §33/§34: the Public Page's own
 * Performance listing - deliberately excludes `capacity` (§34's non-
 * disclosure list explicitly names "Performance capacityそのもの") and
 * `remarks`/`symbol` (internal management detail), unlike the admin-side
 * `PerformanceResult`.
 */
final class PublicPerformanceResult
{
    public string $id;
    public string $performanceDate;
    public string $startTime;
    public ?string $endTime;
    public ?string $symbol;
    public ?string $symbolDescription;
    public string $status;

    private function __construct(string $id, string $performanceDate, string $startTime, ?string $endTime, ?string $symbol, ?string $symbolDescription, string $status)
    {
        $this->id = $id;
        $this->performanceDate = $performanceDate;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->symbol = $symbol;
        $this->symbolDescription = $symbolDescription;
        $this->status = $status;
    }

    public static function fromDomain(Performance $performance): self
    {
        return new self(
            $performance->id()->toString(),
            $performance->performanceDate()->format('Y-m-d'),
            $performance->startTime(),
            $performance->endTime(),
            $performance->symbol(),
            $performance->symbolDescription(),
            $performance->status()->toString()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'performance_date' => $this->performanceDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'symbol' => $this->symbol,
            'symbol_description' => $this->symbolDescription,
            'status' => $this->status,
        ];
    }
}
