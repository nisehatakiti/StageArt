<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseReview;

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
    /** 修正8 approval workflow fields (see Domain\Expense\Expense). */
    public int $revision = 0;
    public ?string $submittedBy = null;
    public ?string $submittedAt = null;
    public ?int $requestedAmount = null;
    public ?int $approvedAmount = null;
    public ?string $approvedBy = null;
    public ?string $approvedAt = null;
    public ?string $rejectedBy = null;
    public ?string $rejectedAt = null;
    public ?string $rejectionReason = null;
    public ?string $postedJournalEntryId = null;
    /** @var array<int, array<string, mixed>> oldest first */
    public array $reviews = [];

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
        $result = new self(
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

        $result->revision = $expense->revision();
        $result->submittedBy = $expense->submittedBy() !== null ? $expense->submittedBy()->toString() : null;
        $result->submittedAt = $expense->submittedAt() !== null ? $expense->submittedAt()->format(DATE_ATOM) : null;
        $result->requestedAmount = $expense->requestedAmount();
        $result->approvedAmount = $expense->approvedAmount();
        $result->approvedBy = $expense->approvedBy() !== null ? $expense->approvedBy()->toString() : null;
        $result->approvedAt = $expense->approvedAt() !== null ? $expense->approvedAt()->format(DATE_ATOM) : null;
        $result->rejectedBy = $expense->rejectedBy() !== null ? $expense->rejectedBy()->toString() : null;
        $result->rejectedAt = $expense->rejectedAt() !== null ? $expense->rejectedAt()->format(DATE_ATOM) : null;
        $result->rejectionReason = $expense->rejectionReason();
        $result->postedJournalEntryId = $expense->postedJournalEntryId() !== null ? $expense->postedJournalEntryId()->toString() : null;
        $result->reviews = array_map(
            static fn (ExpenseReview $review): array => [
                'revision' => $review->revision(),
                'action' => $review->action(),
                'requested_amount' => $review->requestedAmount(),
                'approved_amount' => $review->approvedAmount(),
                'lines' => $review->linesSnapshot(),
                'reason' => $review->reason(),
                'actor_person_id' => $review->actor()->toString(),
                'acted_at' => $review->actedAt()->format(DATE_ATOM),
            ],
            $expense->reviews()
        );

        return $result;
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
            'revision' => $this->revision,
            'submitted_by' => $this->submittedBy,
            'submitted_at' => $this->submittedAt,
            'requested_amount' => $this->requestedAmount,
            'approved_amount' => $this->approvedAmount,
            'approved_by' => $this->approvedBy,
            'approved_at' => $this->approvedAt,
            'rejected_by' => $this->rejectedBy,
            'rejected_at' => $this->rejectedAt,
            'rejection_reason' => $this->rejectionReason,
            'posted_journal_entry_id' => $this->postedJournalEntryId,
            'reviews' => $this->reviews,
        ];
    }
}
