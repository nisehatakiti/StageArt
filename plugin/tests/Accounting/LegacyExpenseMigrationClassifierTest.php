<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigrationClassifier as C;

/**
 * Schema 1.44.0: which pre-workflow Expenses are carried over
 * automatically, and which are left for a person to review.
 */
final class LegacyExpenseMigrationClassifierTest extends TestCase
{
    private const EXPENSE_ID = '11111111-1111-4111-8111-111111111111';
    private const PRODUCTION_ID = '22222222-2222-4222-8222-222222222222';

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
        ], $overrides);
    }

    /**
     * @param int[] $amounts
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $amounts = [3000, 2000]): array
    {
        return array_map(static fn (int $amount): array => ['account_id' => 'acc', 'amount' => (string) $amount], $amounts);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function entry(array $overrides = [], int $debit = 5000, int $credit = 5000): array
    {
        return array_merge([
            'id' => 'je-1',
            'status' => 'POSTED',
            'production_id' => self::PRODUCTION_ID,
            'lines' => [
                ['debit_credit' => 'DEBIT', 'amount' => (string) $debit],
                ['debit_credit' => 'CREDIT', 'amount' => (string) $credit],
            ],
        ], $overrides);
    }

    public function test_confirmed_with_exactly_one_matching_posted_entry_is_migrated(): void
    {
        $result = C::classify($this->expense(), $this->lines(), [$this->entry()]);

        $this->assertSame(C::MIGRATE_TO_POSTED, $result['decision']);
        $this->assertSame('je-1', $result['journal_entry_id']);
        $this->assertSame(5000, $result['amount']);
    }

    public function test_draft_is_left_as_draft(): void
    {
        $this->assertSame(C::LEAVE_DRAFT, C::classify($this->expense(['status' => 'DRAFT']), $this->lines(), [])['decision']);
    }

    /**
     * @dataProvider workflowStatuses
     */
    public function test_rows_already_in_the_workflow_are_not_touched(string $status): void
    {
        $this->assertSame(C::NOT_LEGACY, C::classify($this->expense(['status' => $status]), $this->lines(), [])['decision']);
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
     */
    public function test_anything_uncertain_is_left_for_manual_review(array $expense, array $lines, array $entries, string $reason): void
    {
        $result = C::classify($expense, $lines, $entries);

        $this->assertSame(C::MANUAL_REVIEW, $result['decision']);
        $this->assertSame($reason, $result['reason']);
        $this->assertNull($result['journal_entry_id']);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<int, array<string, mixed>>, array<int, array<string, mixed>>, string}>
     */
    public function manualCases(): array
    {
        return [
            'confirmed with only a DRAFT entry' => [$this->expense(), $this->lines(), [$this->entry(['status' => 'DRAFT'])], C::REASON_DRAFT_JOURNAL_ENTRY],
            'confirmed without any entry' => [$this->expense(), $this->lines(), [], C::REASON_NO_JOURNAL_ENTRY],
            'confirmed with two entries' => [$this->expense(), $this->lines(), [$this->entry(), $this->entry(['id' => 'je-2'])], C::REASON_MULTIPLE_JOURNAL_ENTRIES],
            'confirmed with a posted and a draft entry' => [$this->expense(), $this->lines(), [$this->entry(), $this->entry(['id' => 'je-2', 'status' => 'DRAFT'])], C::REASON_MULTIPLE_JOURNAL_ENTRIES],
            'entry was reversed' => [$this->expense(), $this->lines(), [$this->entry(['status' => 'REVERSED'])], C::REASON_REVERSED_JOURNAL_ENTRY],
            'entry total differs from the lines' => [$this->expense(), $this->lines([3000, 1000]), [$this->entry()], C::REASON_AMOUNT_MISMATCH],
            'entry is unbalanced' => [$this->expense(), $this->lines(), [$this->entry([], 5000, 4000)], C::REASON_UNBALANCED_JOURNAL_ENTRY],
            'entry belongs to another production' => [$this->expense(), $this->lines(), [$this->entry(['production_id' => 'other'])], C::REASON_PRODUCTION_MISMATCH],
            'confirmer missing' => [$this->expense(['confirmed_by' => null]), $this->lines(), [$this->entry()], C::REASON_MISSING_CONFIRMATION],
            'confirmation time missing' => [$this->expense(['confirmed_at' => null]), $this->lines(), [$this->entry()], C::REASON_MISSING_CONFIRMATION],
            'no lines' => [$this->expense(), [], [$this->entry()], C::REASON_NO_LINES],
            'unknown status' => [$this->expense(['status' => 'APPROVED']), $this->lines(), [$this->entry()], C::REASON_UNKNOWN_STATUS],
        ];
    }
}
