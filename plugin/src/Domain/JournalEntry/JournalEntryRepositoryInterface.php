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

    /**
     * Phase 4 Check-in/精算/会計連携: the Accounting Idempotency lookup
     * CheckIn.md's "# Duplicate Accounting" requires ("同一
     * CheckInCompletedから...二重計上してはならない") - given a Check-in's
     * own identity as `sourceEventType`/`sourceEventId`, finds whether a
     * Journal Entry was already generated for it, so re-processing the
     * same Check-in never creates a second entry. Also used by Check-in
     * Reversal to find the original entry to reverse.
     */
    public function findBySourceEvent(string $sourceEventType, string $sourceEventId): ?JournalEntry;
}
