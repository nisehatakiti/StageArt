<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

final class CreatePerformanceCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $performanceDate;
    public string $startTime;
    public ?string $endTime;
    /**
     * Null means "inherit the parent Production's own capacity" (§10) -
     * the Use Case resolves the actual stored value, never this Command.
     */
    public ?int $capacity;
    public ?string $remarks;
    public ?string $symbol;

    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        string $performanceDate,
        string $startTime,
        ?string $endTime,
        ?int $capacity,
        ?string $remarks,
        ?string $symbol
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->performanceDate = $performanceDate;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->capacity = $capacity;
        $this->remarks = $remarks;
        $this->symbol = $symbol;
    }
}
