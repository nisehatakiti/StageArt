<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * Expense.md: Aggregate Root for the "現場からの実績入力" side of
 * accounting. ExpenseLine is a child Entity with no independent
 * Repository. Deliberately minimal for this Phase: Receipt (0..1) and
 * the ReimbursementClaim-on-Payer-Person path (Expense.md "Confirmation
 * and Reimbursement") are Blueprint-defined but not implemented here -
 * payerPersonId is stored (so a future phase can add ReimbursementClaim
 * generation without a schema change) but confirming an Expense with a
 * Payer set does not yet generate a ReimbursementClaim. Disclosed as an
 * Open Item, not a silent gap.
 */
final class Expense
{
    private ExpenseId $id;
    private ProductionId $productionId;
    private ?PersonId $payerPersonId;
    private ExpenseStatus $status;
    /** @var ExpenseLine[] */
    private array $lines;
    private PersonId $createdBy;
    private DateTimeImmutable $createdAt;
    private PersonId $updatedBy;
    private DateTimeImmutable $updatedAt;
    private ?PersonId $confirmedBy;
    private ?DateTimeImmutable $confirmedAt;

    /**
     * @param ExpenseLine[] $lines
     */
    private function __construct(
        ExpenseId $id,
        ProductionId $productionId,
        ?PersonId $payerPersonId,
        ExpenseStatus $status,
        array $lines,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt,
        ?PersonId $confirmedBy,
        ?DateTimeImmutable $confirmedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->payerPersonId = $payerPersonId;
        $this->status = $status;
        $this->lines = $lines;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $updatedAt;
        $this->confirmedBy = $confirmedBy;
        $this->confirmedAt = $confirmedAt;
    }

    /**
     * @param ExpenseLine[] $lines
     */
    public static function create(
        ProductionId $productionId,
        array $lines,
        PersonId $createdBy,
        ?PersonId $payerPersonId = null
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            ExpenseId::generate(),
            $productionId,
            $payerPersonId,
            ExpenseStatus::draft(),
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
     * @param ExpenseLine[] $lines
     */
    public static function reconstitute(
        ExpenseId $id,
        ProductionId $productionId,
        ?PersonId $payerPersonId,
        ExpenseStatus $status,
        array $lines,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt,
        ?PersonId $confirmedBy,
        ?DateTimeImmutable $confirmedAt
    ): self {
        return new self(
            $id,
            $productionId,
            $payerPersonId,
            $status,
            $lines,
            $createdBy,
            $createdAt,
            $updatedBy,
            $updatedAt,
            $confirmedBy,
            $confirmedAt
        );
    }

    /**
     * @param ExpenseLine[] $lines
     */
    public function replaceLines(array $lines, PersonId $updatedBy): void
    {
        $this->assertDraft();
        $this->lines = $lines;
        $this->touch($updatedBy);
    }

    /**
     * Expense.md "Confirmed": requires >=1 Line, then transitions
     * DRAFT -> CONFIRMED. The Application layer is responsible for
     * generating the corresponding JournalEntry (Expense.md
     * "Confirmation and Journal Entry": "Expense自身はJournal Entryを直接
     * 生成しない") in the same transaction as this call.
     */
    public function confirm(PersonId $confirmedBy): void
    {
        $this->assertDraft();

        if (count($this->lines) === 0) {
            throw new InvalidArgumentException('An Expense must have at least one Expense Line before it can be confirmed.');
        }

        $this->status = ExpenseStatus::fromString(ExpenseStatus::CONFIRMED);
        $this->confirmedBy = $confirmedBy;
        $this->confirmedAt = new DateTimeImmutable();
        $this->touch($confirmedBy);
    }

    private function assertDraft(): void
    {
        if (! $this->status->equals(ExpenseStatus::draft())) {
            throw new InvalidArgumentException('Expense Lines can only be changed while the Expense is DRAFT.');
        }
    }

    private function touch(PersonId $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function totalAmount(): int
    {
        return array_sum(array_map(static fn (ExpenseLine $line): int => $line->amount(), $this->lines));
    }

    public function id(): ExpenseId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function payerPersonId(): ?PersonId
    {
        return $this->payerPersonId;
    }

    public function status(): ExpenseStatus
    {
        return $this->status;
    }

    public function isConfirmed(): bool
    {
        return $this->status->equals(ExpenseStatus::fromString(ExpenseStatus::CONFIRMED));
    }

    /**
     * @return ExpenseLine[]
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

    public function confirmedBy(): ?PersonId
    {
        return $this->confirmedBy;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }
}
