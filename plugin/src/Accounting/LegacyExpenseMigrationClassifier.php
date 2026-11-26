<?php

declare(strict_types=1);

namespace StageArt\Accounting;

/**
 * Schema 1.44.0 (修正8): decides, for one Expense row stored before the
 * approval workflow existed, whether it can be carried over
 * automatically. Pure - works on plain arrays shaped like the database
 * rows - so every rule is covered by PHPUnit without WordPress.
 *
 * The retired Confirm step (ConfirmExpenseUseCase before 修正8) created
 * one "ExpenseConfirmed" Journal Entry per Expense, in the Production's
 * Organization and for the Production: one DEBIT line per Expense Line
 * (same Account, same amount) and one CREDIT line for the total against
 * a LIABILITY Account (未払金) of that Organization. It was created
 * DRAFT and posted separately.
 *
 * Only a CONFIRMED Expense whose single such Journal Entry is POSTED and
 * matches that shape exactly is migrated (to POSTED, Journal Entry
 * untouched). DRAFT stays DRAFT. Everything else is left exactly as it
 * is for a person to review, with the first failed check as its reason:
 * no Journal Entry is posted, created or corrected by the migration.
 *
 * An Expense whose migration was undone (a MIGRATED entry followed by a
 * MIGRATION_REVERTED one, ExpenseApprovalWorkflow.md Version 1.8) is never
 * migrated again; any other history that mentions the migration - a
 * MIGRATED entry on a CONFIRMED row, a revert without a migration, either
 * of them twice - is inconsistent and left for review.
 */
final class LegacyExpenseMigrationClassifier
{
    public const LEAVE_DRAFT = 'LEAVE_DRAFT';
    public const MIGRATE_TO_POSTED = 'MIGRATE_TO_POSTED';
    public const MANUAL_REVIEW = 'MANUAL_REVIEW';
    public const NOT_LEGACY = 'NOT_LEGACY';

    public const REASON_UNKNOWN_STATUS = 'UNKNOWN_STATUS';
    public const REASON_MISSING_CONFIRMATION = 'MISSING_CONFIRMATION';
    public const REASON_WORKFLOW_COLUMNS_SET = 'WORKFLOW_COLUMNS_SET';
    public const REASON_HISTORY_EXISTS = 'HISTORY_EXISTS';
    public const REASON_MIGRATION_REVERTED = 'MIGRATION_REVERTED';
    public const REASON_HISTORY_INCONSISTENT = 'HISTORY_INCONSISTENT';
    public const REASON_NO_LINES = 'NO_LINES';
    public const REASON_INVALID_EXPENSE_LINE = 'INVALID_EXPENSE_LINE';
    public const REASON_NO_JOURNAL_ENTRY = 'NO_JOURNAL_ENTRY';
    public const REASON_MULTIPLE_JOURNAL_ENTRIES = 'MULTIPLE_JOURNAL_ENTRIES';
    public const REASON_REVERSED_JOURNAL_ENTRY = 'REVERSED_JOURNAL_ENTRY';
    public const REASON_DRAFT_JOURNAL_ENTRY = 'DRAFT_JOURNAL_ENTRY';
    public const REASON_PRODUCTION_MISMATCH = 'PRODUCTION_MISMATCH';
    public const REASON_ORGANIZATION_UNKNOWN = 'ORGANIZATION_UNKNOWN';
    public const REASON_ORGANIZATION_MISMATCH = 'ORGANIZATION_MISMATCH';
    public const REASON_INVALID_JOURNAL_LINE = 'INVALID_JOURNAL_LINE';
    public const REASON_UNBALANCED_JOURNAL_ENTRY = 'UNBALANCED_JOURNAL_ENTRY';
    public const REASON_AMOUNT_MISMATCH = 'AMOUNT_MISMATCH';
    public const REASON_DEBIT_LINES_MISMATCH = 'DEBIT_LINES_MISMATCH';
    public const REASON_CREDIT_LINES_MISMATCH = 'CREDIT_LINES_MISMATCH';
    public const REASON_PAYABLE_ACCOUNT_INVALID = 'PAYABLE_ACCOUNT_INVALID';
    public const REASON_DEBIT_ACCOUNT_INVALID = 'DEBIT_ACCOUNT_INVALID';

    private const WORKFLOW_STATUSES = ['SUBMITTED', 'POSTED', 'REJECTED'];

    /** Columns added by schema 1.44.0 that a pre-workflow row has never had set. */
    private const WORKFLOW_COLUMNS = [
        'submitted_by', 'submitted_at', 'requested_amount', 'approved_amount', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason', 'posted_journal_entry_id',
    ];

    /**
     * Why an Expense with this history is not migrated, or null when it
     * has none.
     *
     * @param array<int, array<string, mixed>> $history
     */
    private static function historyReason(array $history): ?string
    {
        if ($history === []) {
            return null;
        }

        $migrations = array_values(array_filter($history, static fn (array $row): bool => in_array($row['action'] ?? null, ['MIGRATED', 'MIGRATION_REVERTED'], true)));

        if ($migrations === []) {
            return self::REASON_HISTORY_EXISTS;
        }

        $byAction = [];

        foreach ($migrations as $row) {
            $byAction[(string) $row['action']][] = (int) ($row['revision'] ?? -1);
        }

        $undone = count($history) === 2
            && ($byAction['MIGRATED'] ?? []) === [1]
            && ($byAction['MIGRATION_REVERTED'] ?? []) === [1];

        return $undone ? self::REASON_MIGRATION_REVERTED : self::REASON_HISTORY_INCONSISTENT;
    }

    /**
     * @param array<string, mixed> $expense row of stageart_expenses
     * @param array<int, array<string, mixed>> $lines rows of stageart_expense_lines for it
     * @param array<int, array<string, mixed>> $journalEntries every stageart_journal_entries row
     *        with source_event_type 'ExpenseConfirmed' and source_event_id = the Expense id, each
     *        with a 'lines' key holding its stageart_journal_entry_lines rows
     * @param array{organization_id: ?string, accounts: array<string, array<string, mixed>>, history: array<int, array{action: string, revision: int}>} $context
     *        the Organization of the Expense's Production (null when the Production or its
     *        Project is missing), the stageart_accounts rows (by id) of every Account the
     *        Journal Entry lines use, and the action and revision of each of the Expense's
     *        stageart_expense_reviews rows
     * @return array{decision: string, reason: ?string, journal_entry_id: ?string, amount: ?int}
     */
    public static function classify(array $expense, array $lines, array $journalEntries, array $context): array
    {
        $status = (string) ($expense['status'] ?? '');

        if ($status === 'DRAFT') {
            return self::result(self::LEAVE_DRAFT);
        }

        if (in_array($status, self::WORKFLOW_STATUSES, true)) {
            return self::result(self::NOT_LEGACY);
        }

        if ($status !== 'CONFIRMED') {
            return self::manual(self::REASON_UNKNOWN_STATUS);
        }

        if (($expense['confirmed_by'] ?? null) === null || ($expense['confirmed_at'] ?? null) === null) {
            return self::manual(self::REASON_MISSING_CONFIRMATION);
        }

        if ((int) ($expense['revision'] ?? 0) !== 0) {
            return self::manual(self::REASON_WORKFLOW_COLUMNS_SET);
        }

        foreach (self::WORKFLOW_COLUMNS as $column) {
            if (($expense[$column] ?? null) !== null) {
                return self::manual(self::REASON_WORKFLOW_COLUMNS_SET);
            }
        }

        $history = self::historyReason($context['history'] ?? []);

        if ($history !== null) {
            return self::manual($history);
        }

        if (count($lines) === 0) {
            return self::manual(self::REASON_NO_LINES);
        }

        $expenseByAccount = [];

        foreach ($lines as $line) {
            $account = (string) ($line['account_id'] ?? '');
            $amount = (int) ($line['amount'] ?? 0);

            if ($account === '' || $amount <= 0) {
                return self::manual(self::REASON_INVALID_EXPENSE_LINE);
            }

            $expenseByAccount[$account] = ($expenseByAccount[$account] ?? 0) + $amount;
        }

        if (count($journalEntries) === 0) {
            return self::manual(self::REASON_NO_JOURNAL_ENTRY);
        }

        foreach ($journalEntries as $entry) {
            if (($entry['status'] ?? null) === 'REVERSED') {
                return self::manual(self::REASON_REVERSED_JOURNAL_ENTRY);
            }
        }

        if (count($journalEntries) > 1) {
            return self::manual(self::REASON_MULTIPLE_JOURNAL_ENTRIES);
        }

        $entry = $journalEntries[0];

        if (($entry['status'] ?? null) !== 'POSTED') {
            return self::manual(self::REASON_DRAFT_JOURNAL_ENTRY);
        }

        if (($entry['production_id'] ?? null) !== ($expense['production_id'] ?? null)) {
            return self::manual(self::REASON_PRODUCTION_MISMATCH);
        }

        $organizationId = $context['organization_id'] ?? null;

        if ($organizationId === null || $organizationId === '') {
            return self::manual(self::REASON_ORGANIZATION_UNKNOWN);
        }

        if ((string) ($entry['organization_id'] ?? '') !== $organizationId) {
            return self::manual(self::REASON_ORGANIZATION_MISMATCH);
        }

        $debit = 0;
        $credit = 0;
        $debitByAccount = [];
        $creditAccounts = [];

        foreach ($entry['lines'] ?? [] as $entryLine) {
            $side = $entryLine['debit_credit'] ?? null;
            $account = (string) ($entryLine['account_id'] ?? '');
            $amount = (int) ($entryLine['amount'] ?? 0);

            if (! in_array($side, ['DEBIT', 'CREDIT'], true) || $account === '' || $amount <= 0) {
                return self::manual(self::REASON_INVALID_JOURNAL_LINE);
            }

            if ($side === 'DEBIT') {
                $debit += $amount;
                $debitByAccount[$account] = ($debitByAccount[$account] ?? 0) + $amount;
            } else {
                $credit += $amount;
                $creditAccounts[] = $account;
            }
        }

        if ($debit !== $credit || $debit === 0) {
            return self::manual(self::REASON_UNBALANCED_JOURNAL_ENTRY);
        }

        $linesTotal = array_sum($expenseByAccount);

        if ($linesTotal !== $debit) {
            return self::manual(self::REASON_AMOUNT_MISMATCH);
        }

        ksort($expenseByAccount);
        ksort($debitByAccount);

        if ($expenseByAccount !== $debitByAccount) {
            return self::manual(self::REASON_DEBIT_LINES_MISMATCH);
        }

        if (count($creditAccounts) !== 1) {
            return self::manual(self::REASON_CREDIT_LINES_MISMATCH);
        }

        $accounts = $context['accounts'] ?? [];
        $payable = $accounts[$creditAccounts[0]] ?? null;

        if ($payable === null || (string) ($payable['organization_id'] ?? '') !== $organizationId || ($payable['type'] ?? null) !== 'LIABILITY') {
            return self::manual(self::REASON_PAYABLE_ACCOUNT_INVALID);
        }

        foreach (array_keys($debitByAccount) as $account) {
            $row = $accounts[$account] ?? null;

            if ($row === null || (string) ($row['organization_id'] ?? '') !== $organizationId) {
                return self::manual(self::REASON_DEBIT_ACCOUNT_INVALID);
            }
        }

        return [
            'decision' => self::MIGRATE_TO_POSTED,
            'reason' => null,
            'journal_entry_id' => (string) $entry['id'],
            'amount' => $linesTotal,
        ];
    }

    /**
     * @return array{decision: string, reason: ?string, journal_entry_id: ?string, amount: ?int}
     */
    private static function result(string $decision): array
    {
        return ['decision' => $decision, 'reason' => null, 'journal_entry_id' => null, 'amount' => null];
    }

    /**
     * @return array{decision: string, reason: ?string, journal_entry_id: ?string, amount: ?int}
     */
    private static function manual(string $reason): array
    {
        return ['decision' => self::MANUAL_REVIEW, 'reason' => $reason, 'journal_entry_id' => null, 'amount' => null];
    }
}
