<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

/**
 * Builds pre-workflow data in a FakeExpenseMigrationDatabase the way the
 * retired Confirm step left it: Organization "org-1" with Project
 * "project-1" and Production "production-1", an EXPENSE Account
 * "acc-expense" and a LIABILITY Account "acc-payable".
 */
final class LegacyExpenseFixture
{
    private FakeExpenseMigrationDatabase $db;

    public function __construct(FakeExpenseMigrationDatabase $db)
    {
        $this->db = $db;
        $db->tables['stageart_projects'][] = ['id' => 'project-1', 'organization_id' => 'org-1'];
        $db->tables['stageart_productions'][] = ['id' => 'production-1', 'project_id' => 'project-1'];
        $this->account('acc-expense', 'EXPENSE');
        $this->account('acc-payable', 'LIABILITY');
    }

    public function account(string $id, string $type, string $organizationId = 'org-1'): void
    {
        $this->db->tables['stageart_accounts'][] = ['id' => $id, 'organization_id' => $organizationId, 'name' => $id, 'type' => $type];
    }

    /**
     * @param int[] $lineAmounts
     * @param array<string, mixed> $overrides
     */
    public function expense(string $id, string $status, array $lineAmounts, array $overrides = []): void
    {
        $legacy = in_array($status, ['CONFIRMED', 'APPROVED'], true);
        $this->db->tables['stageart_expenses'][] = array_merge([
            'id' => $id,
            'production_id' => 'production-1',
            'payer_person_id' => null,
            'status' => $status,
            'created_by' => 'creator',
            'created_at' => '2026-09-01 10:00:00',
            'updated_by' => 'creator',
            'updated_at' => '2026-09-01 10:00:00',
            'confirmed_by' => $legacy ? 'manager' : null,
            'confirmed_at' => $legacy ? '2026-09-02 11:00:00' : null,
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

        foreach ($lineAmounts as $index => $amount) {
            $this->db->tables['stageart_expense_lines'][] = [
                'id' => "{$id}-line-{$index}", 'expense_id' => $id, 'account_id' => 'acc-expense', 'amount' => (string) $amount, 'description' => '小道具',
            ];
        }
    }

    /**
     * The entry the Confirm step built for `$amounts` (one DEBIT per
     * amount, one CREDIT to 未払金 for the total), or `$lines` as given.
     *
     * @param int[] $amounts
     * @param array<int, array{string, string, int}>|null $lines [account_id, DEBIT|CREDIT, amount]
     * @param array<string, mixed> $overrides
     */
    public function entry(string $id, string $expenseId, string $status, array $amounts, ?array $lines = null, array $overrides = []): void
    {
        $this->db->tables['stageart_journal_entries'][] = array_merge([
            'id' => $id,
            'organization_id' => 'org-1',
            'production_id' => 'production-1',
            'status' => $status,
            'source_event_type' => 'ExpenseConfirmed',
            'source_event_id' => $expenseId,
        ], $overrides);

        if ($lines === null) {
            $lines = array_map(static fn (int $amount): array => ['acc-expense', 'DEBIT', $amount], $amounts);
            $lines[] = ['acc-payable', 'CREDIT', array_sum($amounts)];
        }

        foreach ($lines as $index => [$account, $side, $amount]) {
            $this->db->tables['stageart_journal_entry_lines'][] = [
                'id' => "{$id}-line-{$index}", 'journal_entry_id' => $id, 'account_id' => $account, 'debit_credit' => $side, 'amount' => (string) $amount,
            ];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function expensesById(): array
    {
        return array_column($this->db->tables['stageart_expenses'], null, 'id');
    }

    /**
     * @return array{entries: int, lines: int, debit: int, credit: int}
     */
    public function journalTotals(): array
    {
        $lines = $this->db->tables['stageart_journal_entry_lines'];
        $sum = static fn (string $side): int => array_sum(array_map(
            static fn (array $line): int => $line['debit_credit'] === $side ? (int) $line['amount'] : 0,
            $lines
        ));

        return [
            'entries' => count($this->db->tables['stageart_journal_entries']),
            'lines' => count($lines),
            'debit' => $sum('DEBIT'),
            'credit' => $sum('CREDIT'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historyOf(string $expenseId): array
    {
        return array_values(array_filter($this->db->tables['stageart_expense_reviews'], static fn (array $r): bool => $r['expense_id'] === $expenseId));
    }
}
