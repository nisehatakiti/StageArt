<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryId;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\JournalEntry\JournalEntryStatus;
use StageArt\Domain\Production\ProductionId;

final class InMemoryJournalEntryRepository implements JournalEntryRepositoryInterface
{
    /** @var array<string, JournalEntry> */
    private array $entries = [];

    public function save(JournalEntry $entry): void
    {
        $this->entries[$entry->id()->toString()] = $entry;
    }

    public function findById(JournalEntryId $id): ?JournalEntry
    {
        return $this->entries[$id->toString()] ?? null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (JournalEntry $entry): bool => $entry->productionId() !== null
                && $entry->productionId()->equals($productionId)
        ));
    }

    public function findPostedByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (JournalEntry $entry): bool => $entry->productionId() !== null
                && $entry->productionId()->equals($productionId)
                && $entry->status()->equals(JournalEntryStatus::fromString(JournalEntryStatus::POSTED))
        ));
    }
}
