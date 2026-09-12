<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use StageArt\Domain\Performance\Performance;

final class PerformanceResult
{
    public string $id;
    public string $productionId;
    public string $performanceDate;
    public string $startTime;
    public ?string $endTime;
    public int $capacity;
    public ?string $remarks;
    public ?string $symbol;
    public string $status;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $productionId,
        string $performanceDate,
        string $startTime,
        ?string $endTime,
        int $capacity,
        ?string $remarks,
        ?string $symbol,
        string $status,
        string $createdAt,
        string $updatedAt
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

    public static function fromDomain(Performance $performance): self
    {
        return new self(
            $performance->id()->toString(),
            $performance->productionId()->toString(),
            $performance->performanceDate()->format('Y-m-d'),
            $performance->startTime(),
            $performance->endTime(),
            $performance->capacity(),
            $performance->remarks(),
            $performance->symbol(),
            $performance->status()->toString(),
            $performance->createdAt()->format(DATE_ATOM),
            $performance->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'production_id' => $this->productionId,
            'performance_date' => $this->performanceDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'capacity' => $this->capacity,
            'remarks' => $this->remarks,
            'symbol' => $this->symbol,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
