<?php

declare(strict_types=1);

namespace StageArt\Accounting;

/**
 * Schema 1.44.0 (修正8): decides, for one Expense row stored before the
 * approval workflow existed, whether it can be carried over
 * automatically. Pure - works on plain arrays shaped like the database
 * rows - so every rule is covered by PHPUnit without WordPress.
 *
 * Only a CONFIRMED Expense whose single "ExpenseConfirmed" Journal Entry
 * is already POSTED, balanced and equal to the Expense's Line total is
 * migrated (to POSTED, Journal Entry untouched). DRAFT stays DRAFT.
 * Everything else is left exactly as it is for a person to review: no
 * Journal Entry is posted, created or changed by the migration.
 */
final class LegacyExpenseMigrationClassifier
{
    public const LEAVE_DRAFT = 'LEAVE_DRAFT';
    public const MIGRATE_TO_POSTED = 'MIGRATE_TO_POSTED';
    public const MANUAL_REVIEW = 'MANUAL_REVIEW';
    public const NOT_LEGACY = 'NOT_LEGACY';

    public const REASON_UNKNOWN_STATUS = 'UNKNOWN_STATUS';
    public const REASON_MISSING_CONFIRMATION = 'MISSING_CONFIRMATION';
    public const REASON_NO_LINES = 'NO_LINES';
    public const REASON_NO_JOURNAL_ENTRY = 'NO_JOURNAL_ENTRY';
    public const REASON_MULTIPLE_JOURNAL_ENTRIES = 'MULTIPLE_JOURNAL_ENTRIES';
    public const REASON_REVERSED_JOURNAL_ENTRY = 'REVERSED_JOURNAL_ENTRY';
    public const REASON_DRAFT_JOURNAL_ENTRY = 'DRAFT_JOURNAL_ENTRY';
    public const REASON_PRODUCTION_MISMATCH = 'PRODUCTION_MISMATCH';
    public const REASON_UNBALANCED_JOURNAL_ENTRY = 'UNBALANCED_JOURNAL_ENTRY';
    public const REASON_AMOUNT_MISMATCH = 'AMOUNT_MISMATCH';

    private const WORKFLOW_STATUSES = ['SUBMITTED', 'POSTED', 'REJECTED'];

    /**
     * @param array<string, mixed> $expense row of stageart_expenses
     * @param array<int, array<string, mixed>> $lines rows of stageart_expense_lines for it
     * @param array<int, array<string, mixed>> $journalEntries every stageart_journal_entries row
     *        with source_event_type 'ExpenseConfirmed' and source_event_id = the Expense id, each
     *        with a 'lines' key holding its stageart_journal_entry_lines rows
     * @return array{decision: string, reason: ?string, journal_entry_id: ?string, amount: ?int}
     */
    public static function classify(array $expense, array $lines, array $journalEntries): array
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

        if (count($lines) === 0) {
            return self::manual(self::REASON_NO_LINES);
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

        $debit = 0;
        $credit = 0;

        foreach ($entry['lines'] ?? [] as $entryLine) {
            if (($entryLine['debit_credit'] ?? null) === 'DEBIT') {
                $debit += (int) $entryLine['amount'];
            } elseif (($entryLine['debit_credit'] ?? null) === 'CREDIT') {
                $credit += (int) $entryLine['amount'];
            }
        }

        if ($debit !== $credit || $debit === 0) {
            return self::manual(self::REASON_UNBALANCED_JOURNAL_ENTRY);
        }

        $linesTotal = array_sum(array_map(static fn (array $line): int => (int) $line['amount'], $lines));

        if ($linesTotal !== $debit) {
            return self::manual(self::REASON_AMOUNT_MISMATCH);
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
