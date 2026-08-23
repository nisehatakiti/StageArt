<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Domain\Expense\Expense;

final class ExpenseResult
{
    public string $id;
    public string $productionId;
    public ?string $payerPersonId;
    public string $status;
    /** @var ExpenseLineResult[] */
    public array $lines;
    public int $totalAmount;
    public string $createdBy;
    public string $createdAt;
    public string $updatedBy;
    public string $updatedAt;
    public ?string $confirmedBy;
    public ?string $confirmedAt;

    /**
     * @param ExpenseLineResult[] $lines
     */
    private function __construct(
        string $id,
        string $productionId,
        ?string $payerPersonId,
        string $status,
        array $lines,
        int $totalAmount,
        string $createdBy,
        string $createdAt,
        string $updatedBy,
        string $updatedAt,
        ?string $confirmedBy,
        ?string $confirmedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->payerPersonId = $payerPersonId;
        $this->status = $status;
        $this->lines = $lines;
        $this->totalAmount = $totalAmount;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $updatedAt;
        $this->confirmedBy = $confirmedBy;
        $this->confirmedAt = $confirmedAt;
    }

    public static function fromDomain(Expense $expense): self
    {
        return new self(
            $expense->id()->toString(),
            $expense->productionId()->toString(),
            $expense->payerPersonId() !== null ? $expense->payerPersonId()->toString() : null,
            $expense->status()->toString(),
            array_map(static fn ($line): ExpenseLineResult => ExpenseLineResult::fromDomain($line), $expense->lines()),
            $expense->totalAmount(),
            $expense->createdBy()->toString(),
            $expense->createdAt()->format(DATE_ATOM),
            $expense->updatedBy()->toString(),
            $expense->updatedAt()->format(DATE_ATOM),
            $expense->confirmedBy() !== null ? $expense->confirmedBy()->toString() : null,
            $expense->confirmedAt() !== null ? $expense->confirmedAt()->format(DATE_ATOM) : null
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
            'payer_person_id' => $this->payerPersonId,
            'status' => $this->status,
            'lines' => array_map(static fn (ExpenseLineResult $line): array => $line->toArray(), $this->lines),
            'total_amount' => $this->totalAmount,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_by' => $this->updatedBy,
            'updated_at' => $this->updatedAt,
            'confirmed_by' => $this->confirmedBy,
            'confirmed_at' => $this->confirmedAt,
        ];
    }
}
