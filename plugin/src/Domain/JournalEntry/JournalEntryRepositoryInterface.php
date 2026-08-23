<?php

declare(strict_types=1);

namespace StageArt\Domain\JournalEntry;

use StageArt\Domain\Production\ProductionId;

interface JournalEntryRepositoryInterface
{
    public function save(JournalEntry $entry): void;

    public function findById(JournalEntryId $id): ?JournalEntry;

    /**
     * @return JournalEntry[]
     */
    public function findByProductionId(ProductionId $productionId): array;

    /**
     * Actual aggregation source - POSTED-only Lines for a Production.
     * Bulk/Aggregate Query per this Phase's N+1 avoidance requirement
     * (no per-Account SQL round trip).
     *
     * @return JournalEntry[]
     */
    public function findPostedByProductionId(ProductionId $productionId): array;
}
