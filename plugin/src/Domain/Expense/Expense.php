<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\JournalEntry\JournalEntryId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * Expense.md: Aggregate Root for the "現場からの実績入力" side of
 * accounting. ExpenseLine is a child Entity with no independent
 * Repository. Receipt (0..1) and ReimbursementClaim generation are still
 * not implemented; payerPersonId is stored as the Person the Expense
 * reimburses.
 *
 * Status lifecycle (ExpenseApprovalWorkflow.md "Expense Approval
 * Lifecycle" and the 修正8 confirmed decisions):
 *
 *   DRAFT --submit--> SUBMITTED --approve--> POSTED (terminal)
 *     ^                  |  \--reject--> REJECTED --resubmit--> SUBMITTED
 *     +----withdraw------+
 *
 * - DRAFT is an unsubmitted 下書き. Creating an Expense never submits it.
 * - The applicant may edit a SUBMITTED Expense before approval; each such
 *   edit is a new submission revision with its own requested amount.
 * - Approval takes the whole Expense's approved total. With more than one
 *   Line a changed total is refused, because no rule says how to spread
 *   it over the Lines. 0 is never an approved amount.
 * - The approver must not be the submitter, the creator, or the payer.
 * - CONFIRMED (the old Confirm step's status) loads, but every
 *   transition out of it is refused.
 *
 * Every submit / withdraw / approve / reject appends an ExpenseReview,
 * so the requested amount, approved amount and 再申請 history survive
 * later edits. New entries are collected in pendingReviews() for the
 * Repository to persist.
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
    private int $revision;
    private ?PersonId $submittedBy;
    private ?DateTimeImmutable $submittedAt;
    private ?int $requestedAmount;
    private ?int $approvedAmount;
    private ?PersonId $approvedBy;
    private ?DateTimeImmutable $approvedAt;
    private ?PersonId $rejectedBy;
    private ?DateTimeImmutable $rejectedAt;
    private ?string $rejectionReason;
    private ?JournalEntryId $postedJournalEntryId;
    /** @var ExpenseReview[] */
    private array $reviews;
    /** @var ExpenseReview[] */
    private array $pendingReviews = [];
    /** Status and revision as last loaded from / written to storage; null for a new Expense. */
    private ?string $persistedStatus = null;
    private ?int $persistedRevision = null;

    /**
     * @param ExpenseLine[] $lines
     * @param ExpenseReview[] $reviews
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
        ?DateTimeImmutable $confirmedAt,
        int $revision,
        ?PersonId $submittedBy,
        ?DateTimeImmutable $submittedAt,
        ?int $requestedAmount,
        ?int $approvedAmount,
        ?PersonId $approvedBy,
        ?DateTimeImmutable $approvedAt,
        ?PersonId $rejectedBy,
        ?DateTimeImmutable $rejectedAt,
        ?string $rejectionReason,
        ?JournalEntryId $postedJournalEntryId,
        array $reviews
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
        $this->revision = $revision;
        $this->submittedBy = $submittedBy;
        $this->submittedAt = $submittedAt;
        $this->requestedAmount = $requestedAmount;
        $this->approvedAmount = $approvedAmount;
        $this->approvedBy = $approvedBy;
        $this->approvedAt = $approvedAt;
        $this->rejectedBy = $rejectedBy;
        $this->rejectedAt = $rejectedAt;
        $this->rejectionReason = $rejectionReason;
        $this->postedJournalEntryId = $postedJournalEntryId;
        $this->reviews = $reviews;
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
            null,
            0,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            []
        );
    }

    /**
     * The workflow fields after `$confirmedAt` default to "never
     * submitted", so a row stored before the workflow columns existed
     * still loads unchanged.
     *
     * @param ExpenseLine[] $lines
     * @param ExpenseReview[] $reviews
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
        ?DateTimeImmutable $confirmedAt,
        int $revision = 0,
        ?PersonId $submittedBy = null,
        ?DateTimeImmutable $submittedAt = null,
        ?int $requestedAmount = null,
        ?int $approvedAmount = null,
        ?PersonId $approvedBy = null,
        ?DateTimeImmutable $approvedAt = null,
        ?PersonId $rejectedBy = null,
        ?DateTimeImmutable $rejectedAt = null,
        ?string $rejectionReason = null,
        ?JournalEntryId $postedJournalEntryId = null,
        array $reviews = []
    ): self {
        $expense = new self(
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
            $confirmedAt,
            $revision,
            $submittedBy,
            $submittedAt,
            $requestedAmount,
            $approvedAmount,
            $approvedBy,
            $approvedAt,
            $rejectedBy,
            $rejectedAt,
            $rejectionReason,
            $postedJournalEntryId,
            $reviews
        );
        $expense->markPersisted();

        return $expense;
    }

    /**
     * Allowed while DRAFT, SUBMITTED (the applicant's 承認前の編集) or
     * REJECTED (修正 before 再申請). Editing a SUBMITTED Expense starts a
     * new submission revision, so an approval always refers to the
     * content and requested amount current at approval time.
     *
     * @param ExpenseLine[] $lines
     */
    public function replaceLines(array $lines, PersonId $updatedBy): void
    {
        $this->assertStatus(
            [ExpenseStatus::DRAFT, ExpenseStatus::SUBMITTED, ExpenseStatus::REJECTED],
            'Expense Lines can only be changed while the Expense is DRAFT, SUBMITTED or REJECTED.'
        );
        $this->assertHasLines($lines);

        $this->lines = $lines;
        $this->touch($updatedBy);

        if ($this->status->is(ExpenseStatus::SUBMITTED)) {
            $this->startSubmissionRevision($updatedBy);
        }
    }

    public function submit(PersonId $submittedBy): void
    {
        $this->assertStatus([ExpenseStatus::DRAFT], 'Only a DRAFT Expense can be submitted.');
        $this->assertHasLines($this->lines);

        $this->status = ExpenseStatus::fromString(ExpenseStatus::SUBMITTED);
        $this->startSubmissionRevision($submittedBy);
    }

    /**
     * 取り下げ: back to DRAFT before any approval. The withdrawn
     * revision stays in the history; submitting again opens a new one.
     */
    public function withdraw(PersonId $withdrawnBy): void
    {
        $this->assertStatus([ExpenseStatus::SUBMITTED], 'Only a SUBMITTED Expense can be withdrawn.');

        $this->status = ExpenseStatus::draft();
        $this->record(ExpenseReview::WITHDRAWN, $this->requestedAmount, null, null, $withdrawnBy);
        $this->touch($withdrawnBy);
    }

    public function resubmit(PersonId $submittedBy): void
    {
        $this->assertStatus([ExpenseStatus::REJECTED], 'Only a REJECTED Expense can be resubmitted.');
        $this->assertHasLines($this->lines);

        $this->status = ExpenseStatus::fromString(ExpenseStatus::SUBMITTED);
        $this->startSubmissionRevision($submittedBy);
    }

    public function reject(PersonId $rejectedBy, string $reason): void
    {
        $this->assertStatus([ExpenseStatus::SUBMITTED], 'Only a SUBMITTED Expense can be rejected.');

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        $this->status = ExpenseStatus::fromString(ExpenseStatus::REJECTED);
        $this->rejectedBy = $rejectedBy;
        $this->rejectedAt = new DateTimeImmutable();
        $this->rejectionReason = $reason;
        $this->record(ExpenseReview::REJECTED, $this->requestedAmount, null, $reason, $rejectedBy);
        $this->touch($rejectedBy);
    }

    /**
     * Validates an approval without changing anything and returns the
     * approved total it would post: the requested amount when
     * `$approvedAmount` is null. `$expectedRevision` is the submission
     * revision the approver reviewed; an Expense edited since then is not
     * approved (修正8 §6.5). The Application layer calls this before
     * building the Journal Entry, then approve() with the same values.
     */
    public function resolveApprovedAmount(PersonId $approver, ?int $approvedAmount, int $expectedRevision): int
    {
        $this->assertStatus([ExpenseStatus::SUBMITTED], 'Only a SUBMITTED Expense can be approved.');

        if ($expectedRevision !== $this->revision) {
            throw new ExpenseStateConflictException(
                "The Expense was changed after it was reviewed (revision {$expectedRevision} reviewed, {$this->revision} current)."
            );
        }

        if ($this->isOwnExpense($approver)) {
            throw new ExpenseSelfApprovalException(
                'An Expense cannot be approved by the Person who submitted or created it, or who is to be reimbursed for it.'
            );
        }

        $requested = (int) $this->requestedAmount;

        if ($approvedAmount === null) {
            return $requested;
        }

        if ($approvedAmount <= 0) {
            throw new InvalidArgumentException('The approved amount must be a positive integer.');
        }

        if ($approvedAmount !== $requested && count($this->lines) > 1) {
            throw new InvalidArgumentException(
                'The approved amount of an Expense with more than one Line cannot differ from the requested amount.'
            );
        }

        return $approvedAmount;
    }

    /**
     * SUBMITTED -> POSTED. Approval and posting are one step
     * (ExpenseApprovalWorkflow.md "Posting Rule"), so the caller passes
     * the already-posted Journal Entry built for the approved amount.
     */
    public function approve(PersonId $approver, ?int $approvedAmount, int $expectedRevision, JournalEntryId $postedJournalEntryId): void
    {
        $amount = $this->resolveApprovedAmount($approver, $approvedAmount, $expectedRevision);

        $this->status = ExpenseStatus::fromString(ExpenseStatus::POSTED);
        $this->approvedAmount = $amount;
        $this->approvedBy = $approver;
        $this->approvedAt = new DateTimeImmutable();
        $this->postedJournalEntryId = $postedJournalEntryId;
        $this->record(ExpenseReview::APPROVED, $this->requestedAmount, $amount, null, $approver);
        $this->touch($approver);
    }

    /**
     * Pre-workflow Confirm step (DRAFT -> CONFIRMED). No production code
     * calls it any more - ConfirmExpenseUseCase now refuses every request
     * (修正8) - it only describes how legacy CONFIRMED rows came to be.
     */
    public function confirm(PersonId $confirmedBy): void
    {
        $this->assertStatus([ExpenseStatus::DRAFT], 'Expense Lines can only be changed while the Expense is DRAFT.');

        if (count($this->lines) === 0) {
            throw new InvalidArgumentException('An Expense must have at least one Expense Line before it can be confirmed.');
        }

        $this->status = ExpenseStatus::fromString(ExpenseStatus::CONFIRMED);
        $this->confirmedBy = $confirmedBy;
        $this->confirmedAt = new DateTimeImmutable();
        $this->touch($confirmedBy);
    }

    /**
     * True when `$person` submitted, created, or is to be reimbursed for
     * this Expense - the people who may not approve it.
     */
    public function isOwnExpense(PersonId $person): bool
    {
        return $this->createdBy->equals($person)
            || ($this->submittedBy !== null && $this->submittedBy->equals($person))
            || ($this->payerPersonId !== null && $this->payerPersonId->equals($person));
    }

    private function startSubmissionRevision(PersonId $submittedBy): void
    {
        $this->revision++;
        $this->submittedBy = $submittedBy;
        $this->submittedAt = new DateTimeImmutable();
        $this->requestedAmount = $this->totalAmount();
        $this->rejectedBy = null;
        $this->rejectedAt = null;
        $this->rejectionReason = null;
        $this->record(ExpenseReview::SUBMITTED, $this->requestedAmount, null, null, $submittedBy);
        $this->touch($submittedBy);
    }

    private function record(string $action, ?int $requestedAmount, ?int $approvedAmount, ?string $reason, PersonId $actor): void
    {
        $review = ExpenseReview::record($this->id, $this->revision, $action, $requestedAmount, $approvedAmount, $this->lines, $reason, $actor);
        $this->reviews[] = $review;
        $this->pendingReviews[] = $review;
    }

    /**
     * @param string[] $allowed
     */
    private function assertStatus(array $allowed, string $message): void
    {
        if (! in_array($this->status->toString(), $allowed, true)) {
            throw new ExpenseStateConflictException($message);
        }
    }

    /**
     * @param ExpenseLine[] $lines
     */
    private function assertHasLines(array $lines): void
    {
        if (count($lines) === 0) {
            throw new InvalidArgumentException('An Expense must have at least one Expense Line.');
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
        return $this->status->is(ExpenseStatus::CONFIRMED);
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

    public function revision(): int
    {
        return $this->revision;
    }

    public function submittedBy(): ?PersonId
    {
        return $this->submittedBy;
    }

    public function submittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function requestedAmount(): ?int
    {
        return $this->requestedAmount;
    }

    public function approvedAmount(): ?int
    {
        return $this->approvedAmount;
    }

    public function approvedBy(): ?PersonId
    {
        return $this->approvedBy;
    }

    public function approvedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function rejectedBy(): ?PersonId
    {
        return $this->rejectedBy;
    }

    public function rejectedAt(): ?DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function postedJournalEntryId(): ?JournalEntryId
    {
        return $this->postedJournalEntryId;
    }

    /**
     * @return ExpenseReview[] the whole history, oldest first
     */
    public function reviews(): array
    {
        return $this->reviews;
    }

    /**
     * @return ExpenseReview[] entries recorded since this object was loaded or last saved
     */
    public function pendingReviews(): array
    {
        return $this->pendingReviews;
    }

    public function persistedStatus(): ?string
    {
        return $this->persistedStatus;
    }

    public function persistedRevision(): ?int
    {
        return $this->persistedRevision;
    }

    /**
     * Called by a Repository once the current state (and pendingReviews())
     * is stored: the stored status / revision become the expectation of
     * the next conditional save, and the pending history is cleared so it
     * is never written twice.
     */
    public function markPersisted(): void
    {
        $this->persistedStatus = $this->status->toString();
        $this->persistedRevision = $this->revision;
        $this->pendingReviews = [];
    }
}
