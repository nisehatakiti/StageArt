<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

final class UpdatePerformanceCommand
{
    public string $performanceId;
    public int $requestedByWordPressUserId;
    public string $performanceDate;
    public string $startTime;
    public ?string $endTime;
    public int $capacity;
    public ?string $remarks;
    public ?string $symbol;
    /** null means "leave Status unchanged" - Status is otherwise only
     * ever moved to CANCELLED via CancelPerformanceUseCase, but §15
     * allows a direct Status edit here too ("Status（権限・業務ルールに応じ
     * た変更）"). */
    public ?string $status;

    public function __construct(
        string $performanceId,
        int $requestedByWordPressUserId,
        string $performanceDate,
        string $startTime,
        ?string $endTime,
        int $capacity,
        ?string $remarks,
        ?string $symbol,
        ?string $status = null
    ) {
        $this->performanceId = $performanceId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->performanceDate = $performanceDate;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->capacity = $capacity;
        $this->remarks = $remarks;
        $this->symbol = $symbol;
        $this->status = $status;
    }
}
