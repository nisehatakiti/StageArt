<?php

declare(strict_types=1);

namespace StageArt\Domain\JournalEntry;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * JournalEntry.md: the Actual/Business-Fact-of-record for accounting.
 * Aggregate Root - JournalEntryLine is a child Entity with no
 * independent Repository. Always belongs to an Organization; a
 * Production association is optional (JournalEntry.md "Production
 * Relationship": "可能な限りProductionとの関連を保持できる", not mandatory
 * for every entry in the wider Blueprint, though every entry this Phase
 * actually generates - via Expense Confirmed - does carry one).
 */
final class JournalEntry
{
    private JournalEntryId $id;
    private OrganizationId $organizationId;
    private ?ProductionId $productionId;
    private DateTimeImmutable $journalDate;
    private string $description;
    private JournalEntryStatus $status;
    private ?string $sourceEventType;
    private ?string $sourceEventId;
    private ?JournalEntryId $reversalOfJournalEntryId;
    /** @var JournalEntryLine[] */
    private array $lines;
    private PersonId $createdBy;
    private DateTimeImmutable $createdAt;
    private PersonId $updatedBy;
    private DateTimeImmutable $updatedAt;
    private ?PersonId $postedBy;
    private ?DateTimeImmutable $postedAt;

    /**
     * @param JournalEntryLine[] $lines
     */
    private function __construct(
        JournalEntryId $id,
        OrganizationId $organizationId,
        ?ProductionId $productionId,
        DateTimeImmutable $journalDate,
        string $description,
        JournalEntryStatus $status,
        ?string $sourceEventType,
        ?string $sourceEventId,
        ?JournalEntryId $reversalOfJournalEntryId,
        array $lines,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt,
        ?PersonId $postedBy,
        ?DateTimeImmutable $postedAt
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

    /**
     * @param JournalEntryLine[] $lines
     */
    public static function create(
        OrganizationId $organizationId,
        ?ProductionId $productionId,
        DateTimeImmutable $journalDate,
        string $description,
        array $lines,
        PersonId $createdBy,
        ?string $sourceEventType = null,
        ?string $sourceEventId = null
    ): self {
        if (trim($description) === '') {
            throw new InvalidArgumentException('JournalEntry description must not be empty.');
        }

        $now = new DateTimeImmutable();

        return new self(
            JournalEntryId::generate(),
            $organizationId,
            $productionId,
            $journalDate,
            $description,
            JournalEntryStatus::draft(),
            $sourceEventType,
            $sourceEventId,
            null,
            $lines,
            $createdBy,
            $now,
            $createdBy,
            $now,
            null,
            null
        );
    }

    /**
     * Reversal.md pattern ("元のJournal Entryを削除・変更しない...元の仕訳と
     * 逆方向のReversal Journal Entryを生成する"): builds the paired,
     * opposite-direction entry directly in REVERSED status (see
     * JournalEntryStatus's docblock for why REVERSED entries - both the
     * reversed original and this reversal record - are excluded from
     * Actual rather than counted as an offsetting pair).
     */
    public static function createReversalOf(self $original, PersonId $reversedBy): self
    {
        if (! $original->status->equals(JournalEntryStatus::fromString(JournalEntryStatus::POSTED))) {
            throw new InvalidArgumentException('Only a POSTED JournalEntry can be reversed.');
        }

        $opposedLines = array_map(
            static fn (JournalEntryLine $line): JournalEntryLine => JournalEntryLine::create(
                $line->accountId(),
                $line->debitCredit()->equals(DebitCredit::debit()) ? DebitCredit::credit() : DebitCredit::debit(),
                $line->amount(),
                $line->description()
            ),
            $original->lines
        );

        $now = new DateTimeImmutable();

        return new self(
            JournalEntryId::generate(),
            $original->organizationId,
            $original->productionId,
            $now,
            'Reversal of: ' . $original->description,
            JournalEntryStatus::fromString(JournalEntryStatus::REVERSED),
            $original->sourceEventType,
            $original->sourceEventId,
            $original->id,
            $opposedLines,
            $reversedBy,
            $now,
            $reversedBy,
            $now,
            $reversedBy,
            $now
        );
    }

    /**
     * @param JournalEntryLine[] $lines
     */
    public static function reconstitute(
        JournalEntryId $id,
        OrganizationId $organizationId,
        ?ProductionId $productionId,
        DateTimeImmutable $journalDate,
        string $description,
        JournalEntryStatus $status,
        ?string $sourceEventType,
        ?string $sourceEventId,
        ?JournalEntryId $reversalOfJournalEntryId,
        array $lines,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt,
        ?PersonId $postedBy,
        ?DateTimeImmutable $postedAt
    ): self {
        return new self(
            $id,
            $organizationId,
            $productionId,
            $journalDate,
            $description,
            $status,
            $sourceEventType,
            $sourceEventId,
            $reversalOfJournalEntryId,
            $lines,
            $createdBy,
            $createdAt,
            $updatedBy,
            $updatedAt,
            $postedBy,
            $postedAt
        );
    }

    /**
     * JournalEntry.md "Posting": validates Total Debit = Total Credit
     * and that both a Debit and a Credit Line exist, then transitions
     * DRAFT -> POSTED.
     */
    public function post(PersonId $postedBy): void
    {
        if (! $this->status->equals(JournalEntryStatus::draft())) {
            throw new InvalidArgumentException('Only a DRAFT JournalEntry can be posted.');
        }

        if (count($this->lines) === 0) {
            throw new InvalidArgumentException('A JournalEntry must have at least one Line before it can be posted.');
        }

        $totalDebit = 0;
        $totalCredit = 0;
        $hasDebit = false;
        $hasCredit = false;

        foreach ($this->lines as $line) {
            if ($line->debitCredit()->equals(DebitCredit::debit())) {
                $totalDebit += $line->amount();
                $hasDebit = true;
            } else {
                $totalCredit += $line->amount();
                $hasCredit = true;
            }
        }

        if (! $hasDebit || ! $hasCredit) {
            throw new InvalidArgumentException('A JournalEntry must have at least one Debit Line and one Credit Line.');
        }

        if ($totalDebit !== $totalCredit) {
            throw new InvalidArgumentException(
                "Total Debit ({$totalDebit}) must equal Total Credit ({$totalCredit}) before posting."
            );
        }

        $this->status = JournalEntryStatus::fromString(JournalEntryStatus::POSTED);
        $this->postedBy = $postedBy;
        $this->postedAt = new DateTimeImmutable();
        $this->touch($postedBy);
    }

    /**
     * Transitions this (POSTED) entry to REVERSED. Callers must
     * separately persist the paired reversal entry built via
     * self::createReversalOf() - both halves of the pair are saved
     * together inside one TransactionManagerInterface::run() call at
     * the Application layer.
     */
    public function markReversed(PersonId $reversedBy): void
    {
        if (! $this->status->equals(JournalEntryStatus::fromString(JournalEntryStatus::POSTED))) {
            throw new InvalidArgumentException('Only a POSTED JournalEntry can be reversed.');
        }

        $this->status = JournalEntryStatus::fromString(JournalEntryStatus::REVERSED);
        $this->touch($reversedBy);
    }

    private function touch(PersonId $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): JournalEntryId
    {
        return $this->id;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function productionId(): ?ProductionId
    {
        return $this->productionId;
    }

    public function journalDate(): DateTimeImmutable
    {
        return $this->journalDate;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): JournalEntryStatus
    {
        return $this->status;
    }

    public function isPosted(): bool
    {
        return $this->status->equals(JournalEntryStatus::fromString(JournalEntryStatus::POSTED));
    }

    public function sourceEventType(): ?string
    {
        return $this->sourceEventType;
    }

    public function sourceEventId(): ?string
    {
        return $this->sourceEventId;
    }

    public function reversalOfJournalEntryId(): ?JournalEntryId
    {
        return $this->reversalOfJournalEntryId;
    }

    /**
     * @return JournalEntryLine[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function createdBy(): PersonId
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedBy(): PersonId
    {
        return $this->updatedBy;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function postedBy(): ?PersonId
    {
        return $this->postedBy;
    }

    public function postedAt(): ?DateTimeImmutable
    {
        return $this->postedAt;
    }
}
