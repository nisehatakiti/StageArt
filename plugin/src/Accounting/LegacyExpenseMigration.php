<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use StageArt\Domain\Shared\Uuid;

/**
 * Schema 1.44.0 (修正8): carries pre-workflow CONFIRMED Expenses over to
 * the approval workflow, following LegacyExpenseMigrationClassifier's
 * decision for each one. Called from AccountingInstaller::install(),
 * i.e. on every schema upgrade, so it must be - and is - safe to run any
 * number of times:
 *
 * - only rows still CONFIRMED are read, and each UPDATE is conditional on
 *   `status = 'CONFIRMED'`, so a row is converted at most once;
 * - the MIGRATED history entry is written with INSERT IGNORE against the
 *   (expense_id, revision, action) unique key;
 * - no Journal Entry is read for writing, posted, created or changed.
 *
 * Rows left for manual review stay CONFIRMED (readable, not editable);
 * their ids and reasons are logged once per run.
 */
final class LegacyExpenseMigration
{
    /**
     * @param \wpdb $wpdb
     * @return array{migrated: string[], manual_review: array<string, string>}
     */
    public static function run(
        $wpdb,
        string $expenses,
        string $expenseLines,
        string $expenseReviews,
        string $journalEntries,
        string $journalEntryLines
    ): array {
        $summary = ['migrated' => [], 'manual_review' => []];

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$expenses} WHERE status = %s", 'CONFIRMED'),
            ARRAY_A
        ) ?: [];

        foreach ($rows as $expense) {
            $lines = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$expenseLines} WHERE expense_id = %s", $expense['id']),
                ARRAY_A
            ) ?: [];

            $entries = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$journalEntries} WHERE source_event_type = %s AND source_event_id = %s",
                    'ExpenseConfirmed',
                    $expense['id']
                ),
                ARRAY_A
            ) ?: [];

            foreach ($entries as $index => $entry) {
                $entries[$index]['lines'] = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM {$journalEntryLines} WHERE journal_entry_id = %s", $entry['id']),
                    ARRAY_A
                ) ?: [];
            }

            $decision = LegacyExpenseMigrationClassifier::classify($expense, $lines, $entries);

            if ($decision['decision'] === LegacyExpenseMigrationClassifier::MANUAL_REVIEW) {
                $summary['manual_review'][(string) $expense['id']] = (string) $decision['reason'];

                continue;
            }

            if ($decision['decision'] !== LegacyExpenseMigrationClassifier::MIGRATE_TO_POSTED) {
                continue;
            }

            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$expenses} SET status = %s, revision = %d, submitted_by = %s, submitted_at = %s,
                    requested_amount = %d, approved_amount = %d, approved_by = %s, approved_at = %s,
                    posted_journal_entry_id = %s
                WHERE id = %s AND status = %s",
                'POSTED',
                1,
                $expense['created_by'],
                $expense['created_at'],
                $decision['amount'],
                $decision['amount'],
                $expense['confirmed_by'],
                $expense['confirmed_at'],
                $decision['journal_entry_id'],
                $expense['id'],
                'CONFIRMED'
            ));

            if ($updated !== 1) {
                continue;
            }

            $snapshot = array_map(
                static fn (array $line): array => [
                    'account_id' => (string) $line['account_id'],
                    'amount' => (int) $line['amount'],
                    'description' => $line['description'] ?? null,
                ],
                $lines
            );

            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$expenseReviews}
                    (id, expense_id, revision, action, requested_amount, approved_amount, lines_snapshot, reason, actor_person_id, acted_at)
                VALUES (%s, %s, %d, %s, %d, %d, %s, NULL, %s, %s)",
                Uuid::generate(),
                $expense['id'],
                1,
                'MIGRATED',
                $decision['amount'],
                $decision['amount'],
                (string) json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                $expense['confirmed_by'],
                $expense['confirmed_at']
            ));

            $summary['migrated'][] = (string) $expense['id'];
        }

        if ($summary['manual_review'] !== []) {
            $pairs = [];

            foreach ($summary['manual_review'] as $id => $reason) {
                $pairs[] = "{$id}={$reason}";
            }

            error_log('[StageArt Schema 1.44.0] Expenses left CONFIRMED for manual review: ' . implode(', ', $pairs));
        }

        return $summary;
    }
}
