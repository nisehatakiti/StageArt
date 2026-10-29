<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryId;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\JournalEntry\JournalEntryStatus;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Production\ProductionId;

final class InMemoryJournalEntryRepository implements JournalEntryRepositoryInterface
{
    /** @var array<string, JournalEntry> */
    private array $entries = [];
    private bool $failNextSave = false;

    public function save(JournalEntry $entry): void
    {
        if ($this->failNextSave) {
            $this->failNextSave = false;

            throw new \RuntimeException('Simulated JournalEntry save failure.');
        }

        $this->entries[$entry->id()->toString()] = $entry;
    }

    /**
     * Test-only: the next save() throws, as WordPressJournalEntryRepository
     * does when a database write fails.
     */
    public function failNextSave(): void
    {
        $this->failNextSave = true;
    }

    /**
     * Test-only, for RollbackingTransactionManager.
     * @return array<string, JournalEntry>
     */
    public function snapshot(): array
    {
        return $this->entries;
    }

    /**
     * @param array<string, JournalEntry> $snapshot
     */
    public function restore(array $snapshot): void
    {
        $this->entries = $snapshot;
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

    public function findPostedWithProductionByOrganizationId(OrganizationId $organizationId): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (JournalEntry $entry): bool => $entry->productionId() !== null
                && $entry->organizationId()->equals($organizationId)
                && $entry->status()->equals(JournalEntryStatus::fromString(JournalEntryStatus::POSTED))
        ));
    }

    public function findBySourceEvent(string $sourceEventType, string $sourceEventId): ?JournalEntry
    {
        $matches = array_values(array_filter(
            $this->entries,
            static fn (JournalEntry $entry): bool => $entry->sourceEventType() === $sourceEventType
                && $entry->sourceEventId() === $sourceEventId
                && ! $entry->status()->equals(JournalEntryStatus::fromString(JournalEntryStatus::REVERSED))
        ));

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (JournalEntry $a, JournalEntry $b): int => $b->createdAt() <=> $a->createdAt());

        return $matches[0];
    }

    /**
     * Test-introspection only - not part of JournalEntryRepositoryInterface.
     * @return JournalEntry[]
     */
    public function all(): array
    {
        return array_values($this->entries);
    }
}
