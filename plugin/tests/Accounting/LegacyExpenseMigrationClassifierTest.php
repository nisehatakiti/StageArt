<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigrationClassifier as C;

/**
 * Schema 1.44.0: which pre-workflow Expenses are carried over
 * automatically, and which are left for a person to review. A migratable
 * Expense has exactly the Journal Entry the retired Confirm step built:
 * one DEBIT per Expense Line (same Account and amount) and one CREDIT for
 * the total against a LIABILITY Account, all in the Production's
 * Organization, and POSTED since.
 */
final class LegacyExpenseMigrationClassifierTest extends TestCase
{
    private const EXPENSE_ID = '11111111-1111-4111-8111-111111111111';
    private const PRODUCTION_ID = '22222222-2222-4222-8222-222222222222';
    private const ORGANIZATION_ID = '33333333-3333-4333-8333-333333333333';

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function expense(array $overrides = []): array
    {
        return array_merge([
            'id' => self::EXPENSE_ID,
            'production_id' => self::PRODUCTION_ID,
            'status' => 'CONFIRMED',
            'created_by' => 'creator',
            'created_at' => '2026-09-01 10:00:00',
            'confirmed_by' => 'manager',
            'confirmed_at' => '2026-09-02 10:00:00',
            'revision' => '0',
            'submitted_by' => null,
            'submitted_at' => null,
            'requested_amount' => null,
            'approved_amount' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'posted_journal_entry_id' => null,
        ], $overrides);
    }

    /**
     * @param int[] $amounts
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $amounts = [3000, 2000], string $account = 'acc'): array
    {
        return array_map(static fn (int $amount): array => ['account_id' => $account, 'amount' => (string) $amount], $amounts);
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array<int, array<string, mixed>>|null $lines
     * @return array<string, mixed>
     */
    private function entry(array $overrides = [], int $debit = 5000, int $credit = 5000, ?array $lines = null): array
    {
        return array_merge([
            'id' => 'je-1',
            'status' => 'POSTED',
            'organization_id' => self::ORGANIZATION_ID,
            'production_id' => self::PRODUCTION_ID,
            'lines' => $lines ?? [
                ['account_id' => 'acc', 'debit_credit' => 'DEBIT', 'amount' => (string) $debit],
                ['account_id' => 'payable', 'debit_credit' => 'CREDIT', 'amount' => (string) $credit],
            ],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{organization_id: ?string, accounts: array<string, array<string, mixed>>, history_count: int}
     */
    private function context(array $overrides = []): array
    {
        return array_merge([
            'organization_id' => self::ORGANIZATION_ID,
            'accounts' => [
                'acc' => ['id' => 'acc', 'organization_id' => self::ORGANIZATION_ID, 'type' => 'EXPENSE'],
                'acc-2' => ['id' => 'acc-2', 'organization_id' => self::ORGANIZATION_ID, 'type' => 'EXPENSE'],
                'payable' => ['id' => 'payable', 'organization_id' => self::ORGANIZATION_ID, 'type' => 'LIABILITY'],
                'foreign' => ['id' => 'foreign', 'organization_id' => 'other-organization', 'type' => 'EXPENSE'],
                'foreign-payable' => ['id' => 'foreign-payable', 'organization_id' => 'other-organization', 'type' => 'LIABILITY'],
                'cash' => ['id' => 'cash', 'organization_id' => self::ORGANIZATION_ID, 'type' => 'ASSET'],
            ],
            'history_count' => 0,
        ], $overrides);
    }

    public function test_confirmed_with_exactly_one_matching_posted_entry_is_migrated(): void
    {
        $result = C::classify($this->expense(), $this->lines(), [$this->entry()], $this->context());

        $this->assertSame(C::MIGRATE_TO_POSTED, $result['decision']);
        $this->assertSame('je-1', $result['journal_entry_id']);
        $this->assertSame(5000, $result['amount']);
    }

    /**
     * The Confirm step wrote one DEBIT per Expense Line, so an entry
     * whose DEBITs add up per Account to the Lines is the same entry
     * however its lines are ordered.
     */
    public function test_debits_are_compared_per_account(): void
    {
        $lines = [['account_id' => 'acc', 'amount' => '3000'], ['account_id' => 'acc-2', 'amount' => '2000']];
        $entry = $this->entry([], 0, 0, [
            ['account_id' => 'acc-2', 'debit_credit' => 'DEBIT', 'amount' => '2000'],
            ['account_id' => 'payable', 'debit_credit' => 'CREDIT', 'amount' => '5000'],
            ['account_id' => 'acc', 'debit_credit' => 'DEBIT', 'amount' => '3000'],
        ]);

        $this->assertSame(C::MIGRATE_TO_POSTED, C::classify($this->expense(), $lines, [$entry], $this->context())['decision']);
    }

    public function test_draft_is_left_as_draft(): void
    {
        $this->assertSame(C::LEAVE_DRAFT, C::classify($this->expense(['status' => 'DRAFT']), $this->lines(), [], $this->context())['decision']);
    }

    /**
     * @dataProvider workflowStatuses
     */
    public function test_rows_already_in_the_workflow_are_not_touched(string $status): void
    {
        $this->assertSame(C::NOT_LEGACY, C::classify($this->expense(['status' => $status]), $this->lines(), [], $this->context())['decision']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function workflowStatuses(): array
    {
        return ['submitted' => ['SUBMITTED'], 'posted' => ['POSTED'], 'rejected' => ['REJECTED']];
    }

    /**
     * @dataProvider manualCases
     *
     * @param array<string, mixed> $expense
     * @param array<int, array<string, mixed>> $lines
     * @param array<int, array<string, mixed>> $entries
     * @param array{organization_id: ?string, accounts: array<string, array<string, mixed>>, history_count: int} $context
     */
    public function test_anything_uncertain_is_left_for_manual_review(array $expense, array $lines, array $entries, array $context, string $reason): void
    {
        $result = C::classify($expense, $lines, $entries, $context);

        $this->assertSame(C::MANUAL_REVIEW, $result['decision']);
        $this->assertSame($reason, $result['reason']);
        $this->assertNull($result['journal_entry_id']);
        $this->assertNull($result['amount']);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<int, array<string, mixed>>, array<int, array<string, mixed>>, array<string, mixed>, string}>
     */
    public function manualCases(): array
    {
        $e = $this->expense();
        $l = $this->lines();
        $c = $this->context();
        $line = static fn (string $account, string $side, $amount): array => ['account_id' => $account, 'debit_credit' => $side, 'amount' => $amount];

        return [
            'confirmed with only a DRAFT entry' => [$e, $l, [$this->entry(['status' => 'DRAFT'])], $c, C::REASON_DRAFT_JOURNAL_ENTRY],
            'confirmed without any entry' => [$e, $l, [], $c, C::REASON_NO_JOURNAL_ENTRY],
            'confirmed with two entries' => [$e, $l, [$this->entry(), $this->entry(['id' => 'je-2'])], $c, C::REASON_MULTIPLE_JOURNAL_ENTRIES],
            'confirmed with a posted and a draft entry' => [$e, $l, [$this->entry(), $this->entry(['id' => 'je-2', 'status' => 'DRAFT'])], $c, C::REASON_MULTIPLE_JOURNAL_ENTRIES],
            'entry was reversed' => [$e, $l, [$this->entry(['status' => 'REVERSED'])], $c, C::REASON_REVERSED_JOURNAL_ENTRY],
            'entry total differs from the lines' => [$e, $this->lines([3000, 1000]), [$this->entry()], $c, C::REASON_AMOUNT_MISMATCH],
            'entry is unbalanced' => [$e, $l, [$this->entry([], 5000, 4000)], $c, C::REASON_UNBALANCED_JOURNAL_ENTRY],
            'entry has no lines' => [$e, $l, [$this->entry([], 0, 0, [])], $c, C::REASON_UNBALANCED_JOURNAL_ENTRY],
            'entry belongs to another production' => [$e, $l, [$this->entry(['production_id' => 'other'])], $c, C::REASON_PRODUCTION_MISMATCH],
            'entry has no production' => [$e, $l, [$this->entry(['production_id' => null])], $c, C::REASON_PRODUCTION_MISMATCH],
            'confirmer missing' => [$this->expense(['confirmed_by' => null]), $l, [$this->entry()], $c, C::REASON_MISSING_CONFIRMATION],
            'confirmation time missing' => [$this->expense(['confirmed_at' => null]), $l, [$this->entry()], $c, C::REASON_MISSING_CONFIRMATION],
            'no lines' => [$e, [], [$this->entry()], $c, C::REASON_NO_LINES],
            'unknown status' => [$this->expense(['status' => 'APPROVED']), $l, [$this->entry()], $c, C::REASON_UNKNOWN_STATUS],

            // the row is not as the pre-workflow code left it
            'revision already set' => [$this->expense(['revision' => '1']), $l, [$this->entry()], $c, C::REASON_WORKFLOW_COLUMNS_SET],
            'already linked to a journal entry' => [$this->expense(['posted_journal_entry_id' => 'je-1']), $l, [$this->entry()], $c, C::REASON_WORKFLOW_COLUMNS_SET],
            'approver already set' => [$this->expense(['approved_by' => 'someone']), $l, [$this->entry()], $c, C::REASON_WORKFLOW_COLUMNS_SET],
            'rejection already recorded' => [$this->expense(['rejection_reason' => 'x']), $l, [$this->entry()], $c, C::REASON_WORKFLOW_COLUMNS_SET],
            'history already exists' => [$e, $l, [$this->entry()], $this->context(['history_count' => 1]), C::REASON_HISTORY_EXISTS],
            'expense line without an amount' => [$e, [['account_id' => 'acc', 'amount' => null]], [$this->entry()], $c, C::REASON_INVALID_EXPENSE_LINE],
            'expense line without an account' => [$e, [['account_id' => '', 'amount' => '5000']], [$this->entry()], $c, C::REASON_INVALID_EXPENSE_LINE],

            // the Organization
            'production or project missing' => [$e, $l, [$this->entry()], $this->context(['organization_id' => null]), C::REASON_ORGANIZATION_UNKNOWN],
            'entry in another organization' => [$e, $l, [$this->entry(['organization_id' => 'other-organization'])], $c, C::REASON_ORGANIZATION_MISMATCH],

            // the entry's lines
            'line with an unknown side' => [$e, $l, [$this->entry([], 0, 0, [$line('acc', 'DR', '5000'), $line('payable', 'CREDIT', '5000')])], $c, C::REASON_INVALID_JOURNAL_LINE],
            'line without an amount' => [$e, $l, [$this->entry([], 0, 0, [$line('acc', 'DEBIT', null), $line('acc', 'DEBIT', '5000'), $line('payable', 'CREDIT', '5000')])], $c, C::REASON_INVALID_JOURNAL_LINE],
            'line with a negative amount' => [$e, $l, [$this->entry([], 0, 0, [$line('acc', 'DEBIT', '6000'), $line('acc', 'DEBIT', '-1000'), $line('payable', 'CREDIT', '5000')])], $c, C::REASON_INVALID_JOURNAL_LINE],
            'line without an account' => [$e, $l, [$this->entry([], 0, 0, [$line('', 'DEBIT', '5000'), $line('payable', 'CREDIT', '5000')])], $c, C::REASON_INVALID_JOURNAL_LINE],
            'debit on another account than the lines' => [$e, $l, [$this->entry([], 0, 0, [$line('acc-2', 'DEBIT', '5000'), $line('payable', 'CREDIT', '5000')])], $c, C::REASON_DEBIT_LINES_MISMATCH],
            'debits split differently across accounts' => [
                $e,
                [['account_id' => 'acc', 'amount' => '3000'], ['account_id' => 'acc-2', 'amount' => '2000']],
                [$this->entry([], 0, 0, [$line('acc', 'DEBIT', '2000'), $line('acc-2', 'DEBIT', '3000'), $line('payable', 'CREDIT', '5000')])],
                $c,
                C::REASON_DEBIT_LINES_MISMATCH,
            ],
            'two credit lines' => [$e, $l, [$this->entry([], 0, 0, [$line('acc', 'DEBIT', '5000'), $line('payable', 'CREDIT', '3000'), $line('payable', 'CREDIT', '2000')])], $c, C::REASON_CREDIT_LINES_MISMATCH],

            // the Accounts
            'credit not to a liability' => [$e, $l, [$this->entry([], 0, 0, [$line('acc', 'DEBIT', '5000'), $line('cash', 'CREDIT', '5000')])], $c, C::REASON_PAYABLE_ACCOUNT_INVALID],
            'credit to another organization' => [$e, $l, [$this->entry([], 0, 0, [$line('acc', 'DEBIT', '5000'), $line('foreign-payable', 'CREDIT', '5000')])], $c, C::REASON_PAYABLE_ACCOUNT_INVALID],
            'credit account missing' => [$e, $l, [$this->entry([], 0, 0, [$line('acc', 'DEBIT', '5000'), $line('gone', 'CREDIT', '5000')])], $c, C::REASON_PAYABLE_ACCOUNT_INVALID],
            'debit to another organization' => [$e, $this->lines([5000], 'foreign'), [$this->entry([], 0, 0, [$line('foreign', 'DEBIT', '5000'), $line('payable', 'CREDIT', '5000')])], $c, C::REASON_DEBIT_ACCOUNT_INVALID],
            'debit account missing' => [$e, $this->lines([5000], 'gone'), [$this->entry([], 0, 0, [$line('gone', 'DEBIT', '5000'), $line('payable', 'CREDIT', '5000')])], $c, C::REASON_DEBIT_ACCOUNT_INVALID],
        ];
    }
}
