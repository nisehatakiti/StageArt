<?php

declare(strict_types=1);

namespace StageArt\Application\JournalEntry;

use StageArt\Domain\JournalEntry\JournalEntry;

final class JournalEntryResult
{
    public string $id;
    public string $organizationId;
    public ?string $productionId;
    public string $journalDate;
    public string $description;
    public string $status;
    public ?string $sourceEventType;
    public ?string $sourceEventId;
    public ?string $reversalOfJournalEntryId;
    /** @var JournalEntryLineResult[] */
    public array $lines;
    public string $createdBy;
    public string $createdAt;
    public string $updatedBy;
    public string $updatedAt;
    public ?string $postedBy;
    public ?string $postedAt;

    /**
     * @param JournalEntryLineResult[] $lines
     */
    private function __construct(
        string $id,
        string $organizationId,
        ?string $productionId,
        string $journalDate,
        string $description,
        string $status,
        ?string $sourceEventType,
        ?string $sourceEventId,
        ?string $reversalOfJournalEntryId,
        array $lines,
        string $createdBy,
        string $createdAt,
        string $updatedBy,
        string $updatedAt,
        ?string $postedBy,
        ?string $postedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->productionId = $productionId;
        $this->journalDate = $journalDate;
        $this->description = $description;
        $this->status = $status;
        $this->sourceEventType = $sourceEventType;
        $this->sourceEventId = $sourceEventId;
        $this->reversalOfJournalEntryId = $reversalOfJournalEntryId;
        $this->lines = $lines;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $updatedAt;
        $this->postedBy = $postedBy;
        $this->postedAt = $postedAt;
    }

    public static function fromDomain(JournalEntry $entry): self
    {
        return new self(
            $entry->id()->toString(),
            $entry->organizationId()->toString(),
            $entry->productionId() !== null ? $entry->productionId()->toString() : null,
            $entry->journalDate()->format(DATE_ATOM),
            $entry->description(),
            $entry->status()->toString(),
            $entry->sourceEventType(),
            $entry->sourceEventId(),
            $entry->reversalOfJournalEntryId() !== null ? $entry->reversalOfJournalEntryId()->toString() : null,
            array_map(static fn ($line): JournalEntryLineResult => JournalEntryLineResult::fromDomain($line), $entry->lines()),
            $entry->createdBy()->toString(),
            $entry->createdAt()->format(DATE_ATOM),
            $entry->updatedBy()->toString(),
            $entry->updatedAt()->format(DATE_ATOM),
            $entry->postedBy() !== null ? $entry->postedBy()->toString() : null,
            $entry->postedAt() !== null ? $entry->postedAt()->format(DATE_ATOM) : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'production_id' => $this->productionId,
            'journal_date' => $this->journalDate,
            'description' => $this->description,
            'status' => $this->status,
            'source_event_type' => $this->sourceEventType,
            'source_event_id' => $this->sourceEventId,
            'reversal_of_journal_entry_id' => $this->reversalOfJournalEntryId,
            'lines' => array_map(static fn (JournalEntryLineResult $line): array => $line->toArray(), $this->lines),
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_by' => $this->updatedBy,
            'updated_at' => $this->updatedAt,
            'posted_by' => $this->postedBy,
            'posted_at' => $this->postedAt,
        ];
    }
}
