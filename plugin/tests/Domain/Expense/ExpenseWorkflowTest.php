<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Expense;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseLine;
use StageArt\Domain\Expense\ExpenseReview;
use StageArt\Domain\Expense\ExpenseSelfApprovalException;
use StageArt\Domain\Expense\ExpenseStateConflictException;
use StageArt\Domain\Expense\ExpenseStatus;
use StageArt\Domain\JournalEntry\JournalEntryId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * 修正8 経費ワークフロー: DRAFT -> SUBMITTED -> POSTED / REJECTED.
 */
final class ExpenseWorkflowTest extends TestCase
{
    private PersonId $applicant;
    private PersonId $approver;

    protected function setUp(): void
    {
        $this->applicant = PersonId::generate();
        $this->approver = PersonId::generate();
    }

    /**
     * @param int[] $amounts
     */
    private function draft(array $amounts = [5000], ?PersonId $payer = null): Expense
    {
        $lines = array_map(static fn (int $amount): ExpenseLine => ExpenseLine::create(AccountId::generate(), $amount), $amounts);

        return Expense::create(ProductionId::generate(), $lines, $this->applicant, $payer);
    }

    /**
     * @param int[] $amounts
     */
    private function submitted(array $amounts = [5000], ?PersonId $payer = null): Expense
    {
        $expense = $this->draft($amounts, $payer);
        $expense->submit($this->applicant);

        return $expense;
    }

    /**
     * @return string[]
     */
    private function actions(Expense $expense): array
    {
        return array_map(static fn (ExpenseReview $review): string => $review->revision() . ':' . $review->action(), $expense->reviews());
    }

    public function test_a_new_expense_is_an_unsubmitted_draft(): void
    {
        $expense = $this->draft();

        $this->assertSame(ExpenseStatus::DRAFT, $expense->status()->toString());
        $this->assertSame(0, $expense->revision());
        $this->assertNull($expense->submittedBy());
        $this->assertNull($expense->requestedAmount());
        $this->assertSame([], $expense->reviews());
    }

    public function test_submit_records_the_requested_total_and_opens_revision_one(): void
    {
        $expense = $this->submitted([1200, 800]);

        $this->assertSame(ExpenseStatus::SUBMITTED, $expense->status()->toString());
        $this->assertSame(1, $expense->revision());
        $this->assertSame(2000, $expense->requestedAmount());
        $this->assertTrue($expense->submittedBy()->equals($this->applicant));
        $this->assertNotNull($expense->submittedAt());
        $this->assertSame(['1:SUBMITTED'], $this->actions($expense));
        $this->assertSame(2000, $expense->reviews()[0]->requestedAmount());
        $this->assertCount(2, $expense->reviews()[0]->linesSnapshot());
    }

    public function test_submit_requires_at_least_one_line(): void
    {
        $expense = Expense::create(ProductionId::generate(), [], $this->applicant);

        $this->expectException(InvalidArgumentException::class);

        $expense->submit($this->applicant);
    }

    public function test_only_a_draft_can_be_submitted(): void
    {
        $expense = $this->submitted();

        $this->expectException(ExpenseStateConflictException::class);

        $expense->submit($this->applicant);
    }

    public function test_approve_without_an_amount_posts_the_requested_total(): void
    {
        $expense = $this->submitted([1200, 800]);
        $journalEntryId = JournalEntryId::generate();

        $expense->approve($this->approver, null, 1, $journalEntryId);

        $this->assertSame(ExpenseStatus::POSTED, $expense->status()->toString());
        $this->assertSame(2000, $expense->approvedAmount());
        $this->assertSame(2000, $expense->requestedAmount());
        $this->assertTrue($expense->approvedBy()->equals($this->approver));
        $this->assertNotNull($expense->approvedAt());
        $this->assertTrue($expense->postedJournalEntryId()->equals($journalEntryId));
        $this->assertSame(['1:SUBMITTED', '1:APPROVED'], $this->actions($expense));
    }

    public function test_a_single_line_expense_can_be_approved_for_a_changed_total_and_keeps_both_amounts(): void
    {
        $expense = $this->submitted([5000]);

        $expense->approve($this->approver, 2500, 1, JournalEntryId::generate());

        $this->assertSame(2500, $expense->approvedAmount());
        $this->assertSame(5000, $expense->requestedAmount());
        $approval = $expense->reviews()[1];
        $this->assertSame(5000, $approval->requestedAmount());
        $this->assertSame(2500, $approval->approvedAmount());
        $this->assertSame(5000, $approval->linesSnapshot()[0]['amount']);
    }

    public function test_a_multi_line_expense_cannot_be_approved_for_a_changed_total(): void
    {
        $expense = $this->submitted([3000, 2000]);

        try {
            $expense->approve($this->approver, 4000, 1, JournalEntryId::generate());
            $this->fail('A changed total on a multi-line Expense must be refused.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(ExpenseStatus::SUBMITTED, $expense->status()->toString());
            $this->assertNull($expense->approvedAmount());
        }
    }

    public function test_a_multi_line_expense_can_be_approved_for_exactly_its_requested_total(): void
    {
        $expense = $this->submitted([3000, 2000]);

        $expense->approve($this->approver, 5000, 1, JournalEntryId::generate());

        $this->assertSame(5000, $expense->approvedAmount());
    }

    /**
     * @dataProvider nonPositiveAmounts
     */
    public function test_zero_or_negative_approved_amount_is_refused(int $amount): void
    {
        $expense = $this->submitted([5000]);

        $this->expectException(InvalidArgumentException::class);

        $expense->approve($this->approver, $amount, 1, JournalEntryId::generate());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonPositiveAmounts(): array
    {
        return ['zero' => [0], 'negative' => [-100]];
    }

    public function test_the_applicant_cannot_approve_their_own_expense(): void
    {
        $expense = $this->submitted();

        $this->expectException(ExpenseSelfApprovalException::class);

        $expense->approve($this->applicant, null, 1, JournalEntryId::generate());
    }

    public function test_the_payer_cannot_approve_the_expense_that_reimburses_them(): void
    {
        $payer = PersonId::generate();
        $expense = $this->submitted([5000], $payer);

        $this->expectException(ExpenseSelfApprovalException::class);

        $expense->approve($payer, null, 1, JournalEntryId::generate());
    }

    public function test_self_approval_check_does_not_change_the_expense(): void
    {
        $expense = $this->submitted();

        try {
            $expense->resolveApprovedAmount($this->applicant, null, 1);
            $this->fail('Self-approval must be refused.');
        } catch (ExpenseSelfApprovalException $exception) {
            $this->assertSame(ExpenseStatus::SUBMITTED, $expense->status()->toString());
            $this->assertSame(['1:SUBMITTED'], $this->actions($expense));
        }
    }

    public function test_approval_of_a_revision_other_than_the_current_one_is_refused(): void
    {
        $expense = $this->submitted([5000]);
        $expense->replaceLines([ExpenseLine::create(AccountId::generate(), 9000)], $this->applicant);

        try {
            $expense->approve($this->approver, null, 1, JournalEntryId::generate());
            $this->fail('Approving the revision the approver reviewed after the applicant edited it must be refused.');
        } catch (ExpenseStateConflictException $exception) {
            $this->assertSame(ExpenseStatus::SUBMITTED, $expense->status()->toString());
            $this->assertNull($expense->approvedAmount());
        }

        $expense->approve($this->approver, null, 2, JournalEntryId::generate());
        $this->assertSame(9000, $expense->approvedAmount());
    }

    public function test_reconstituted_expense_remembers_the_stored_status_and_revision(): void
    {
        $now = new DateTimeImmutable();
        $expense = Expense::reconstitute(
            ExpenseId::generate(),
            ProductionId::generate(),
            null,
            ExpenseStatus::fromString(ExpenseStatus::SUBMITTED),
            [ExpenseLine::create(AccountId::generate(), 3000)],
            $this->applicant,
            $now,
            $this->applicant,
            $now,
            null,
            null,
            3,
            $this->applicant,
            $now,
            3000
        );

        $this->assertSame(ExpenseStatus::SUBMITTED, $expense->persistedStatus());
        $this->assertSame(3, $expense->persistedRevision());

        $expense->reject($this->approver, '不備あり');

        $this->assertSame(ExpenseStatus::SUBMITTED, $expense->persistedStatus());
        $this->assertCount(1, $expense->pendingReviews());

        $expense->markPersisted();

        $this->assertSame(ExpenseStatus::REJECTED, $expense->persistedStatus());
        $this->assertSame([], $expense->pendingReviews());
    }

    public function test_a_new_expense_has_no_stored_status(): void
    {
        $this->assertNull($this->draft()->persistedStatus());
        $this->assertNull($this->draft()->persistedRevision());
    }

    public function test_only_a_submitted_expense_can_be_approved(): void
    {
        $expense = $this->draft();

        $this->expectException(ExpenseStateConflictException::class);

        $expense->approve($this->approver, null, 1, JournalEntryId::generate());
    }

    public function test_a_posted_expense_cannot_be_approved_again(): void
    {
        $expense = $this->submitted();
        $expense->approve($this->approver, null, 1, JournalEntryId::generate());

        $this->expectException(ExpenseStateConflictException::class);

        $expense->approve(PersonId::generate(), null, 1, JournalEntryId::generate());
    }

    public function test_reject_requires_a_reason(): void
    {
        $expense = $this->submitted();

        try {
            $expense->reject($this->approver, '   ');
            $this->fail('A blank rejection reason must be refused.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(ExpenseStatus::SUBMITTED, $expense->status()->toString());
        }
    }

    public function test_reject_records_who_when_and_why(): void
    {
        $expense = $this->submitted();

        $expense->reject($this->approver, ' 領収書がありません ');

        $this->assertSame(ExpenseStatus::REJECTED, $expense->status()->toString());
        $this->assertTrue($expense->rejectedBy()->equals($this->approver));
        $this->assertNotNull($expense->rejectedAt());
        $this->assertSame('領収書がありません', $expense->rejectionReason());
        $this->assertSame(['1:SUBMITTED', '1:REJECTED'], $this->actions($expense));
        $this->assertSame('領収書がありません', $expense->reviews()[1]->reason());
    }

    public function test_a_rejected_expense_is_edited_and_resubmitted_as_a_new_revision(): void
    {
        $expense = $this->submitted([5000]);
        $expense->reject($this->approver, '金額を確認してください');

        $expense->replaceLines([ExpenseLine::create(AccountId::generate(), 4200)], $this->applicant);
        $this->assertSame(ExpenseStatus::REJECTED, $expense->status()->toString());

        $expense->resubmit($this->applicant);

        $this->assertSame(ExpenseStatus::SUBMITTED, $expense->status()->toString());
        $this->assertSame(2, $expense->revision());
        $this->assertSame(4200, $expense->requestedAmount());
        $this->assertNull($expense->rejectionReason());
        $this->assertSame(['1:SUBMITTED', '1:REJECTED', '2:SUBMITTED'], $this->actions($expense));
        $this->assertSame(5000, $expense->reviews()[0]->requestedAmount());
        $this->assertSame('金額を確認してください', $expense->reviews()[1]->reason());
    }

    public function test_editing_a_submitted_expense_opens_a_new_revision_with_the_new_total(): void
    {
        $expense = $this->submitted([5000]);

        $expense->replaceLines([ExpenseLine::create(AccountId::generate(), 6000)], $this->applicant);

        $this->assertSame(ExpenseStatus::SUBMITTED, $expense->status()->toString());
        $this->assertSame(2, $expense->revision());
        $this->assertSame(6000, $expense->requestedAmount());
        $this->assertSame(['1:SUBMITTED', '2:SUBMITTED'], $this->actions($expense));
    }

    public function test_withdraw_returns_a_submitted_expense_to_draft_and_keeps_the_history(): void
    {
        $expense = $this->submitted([5000]);

        $expense->withdraw($this->applicant);

        $this->assertSame(ExpenseStatus::DRAFT, $expense->status()->toString());
        $this->assertSame(['1:SUBMITTED', '1:WITHDRAWN'], $this->actions($expense));

        $expense->submit($this->applicant);

        $this->assertSame(2, $expense->revision());
        $this->assertSame(['1:SUBMITTED', '1:WITHDRAWN', '2:SUBMITTED'], $this->actions($expense));
    }

    public function test_only_a_submitted_expense_can_be_withdrawn(): void
    {
        $expense = $this->draft();

        $this->expectException(ExpenseStateConflictException::class);

        $expense->withdraw($this->applicant);
    }

    public function test_a_posted_expense_can_no_longer_be_edited_rejected_or_withdrawn(): void
    {
        $operations = [
            'edit' => fn (Expense $e) => $e->replaceLines([ExpenseLine::create(AccountId::generate(), 1)], $this->applicant),
            'reject' => fn (Expense $e) => $e->reject($this->approver, 'too late'),
            'withdraw' => fn (Expense $e) => $e->withdraw($this->applicant),
            'resubmit' => fn (Expense $e) => $e->resubmit($this->applicant),
        ];

        foreach ($operations as $name => $operation) {
            $expense = $this->submitted();
            $expense->approve($this->approver, null, 1, JournalEntryId::generate());

            try {
                $operation($expense);
                $this->fail("{$name} must be refused on a POSTED Expense.");
            } catch (ExpenseStateConflictException $exception) {
                $this->assertSame(ExpenseStatus::POSTED, $expense->status()->toString());
            }
        }
    }

    public function test_a_rejected_expense_cannot_be_approved_or_rejected_again(): void
    {
        foreach (['approve', 'reject'] as $operation) {
            $expense = $this->submitted();
            $expense->reject($this->approver, '不備あり');

            try {
                $operation === 'approve'
                    ? $expense->approve(PersonId::generate(), null, 1, JournalEntryId::generate())
                    : $expense->reject(PersonId::generate(), 'again');
                $this->fail("{$operation} must be refused on a REJECTED Expense.");
            } catch (ExpenseStateConflictException $exception) {
                $this->assertSame(ExpenseStatus::REJECTED, $expense->status()->toString());
            }
        }
    }

    public function test_a_legacy_confirmed_expense_loads_but_refuses_every_transition(): void
    {
        $now = new DateTimeImmutable();
        $operations = [
            'submit' => fn (Expense $e) => $e->submit($this->applicant),
            'approve' => fn (Expense $e) => $e->approve($this->approver, null, 1, JournalEntryId::generate()),
            'reject' => fn (Expense $e) => $e->reject($this->approver, 'x'),
            'withdraw' => fn (Expense $e) => $e->withdraw($this->applicant),
            'resubmit' => fn (Expense $e) => $e->resubmit($this->applicant),
            'edit' => fn (Expense $e) => $e->replaceLines([ExpenseLine::create(AccountId::generate(), 1)], $this->applicant),
        ];

        foreach ($operations as $name => $operation) {
            $legacy = Expense::reconstitute(
                ExpenseId::generate(),
                ProductionId::generate(),
                null,
                ExpenseStatus::fromString(ExpenseStatus::CONFIRMED),
                [ExpenseLine::create(AccountId::generate(), 3000)],
                $this->applicant,
                $now,
                $this->applicant,
                $now,
                $this->approver,
                $now
            );

            $this->assertSame(0, $legacy->revision());

            try {
                $operation($legacy);
                $this->fail("{$name} must be refused on a legacy CONFIRMED Expense.");
            } catch (ExpenseStateConflictException $exception) {
                $this->assertSame(ExpenseStatus::CONFIRMED, $legacy->status()->toString());
                $this->assertSame([], $legacy->pendingReviews());
            }
        }
    }

    public function test_pending_reviews_hold_only_entries_recorded_after_loading(): void
    {
        $expense = $this->submitted();
        $now = new DateTimeImmutable();

        $loaded = Expense::reconstitute(
            $expense->id(),
            $expense->productionId(),
            null,
            $expense->status(),
            $expense->lines(),
            $this->applicant,
            $now,
            $this->applicant,
            $now,
            null,
            null,
            $expense->revision(),
            $expense->submittedBy(),
            $expense->submittedAt(),
            $expense->requestedAmount(),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $expense->reviews()
        );

        $this->assertSame([], $loaded->pendingReviews());

        $loaded->reject($this->approver, '不備あり');

        $this->assertCount(1, $loaded->pendingReviews());
        $this->assertSame(ExpenseReview::REJECTED, $loaded->pendingReviews()[0]->action());
        $this->assertCount(2, $loaded->reviews());
    }
}
