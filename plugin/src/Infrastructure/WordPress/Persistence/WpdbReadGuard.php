<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use RuntimeException;
use wpdb;

/**
 * PR #7 review: $wpdb answers a failed read (a lost connection, a lock
 * wait timeout, a deadlock, a missing table) with no rows - get_row()
 * null, get_results() an empty array - exactly as it answers a read that
 * found nothing. Only $wpdb->last_error, which wpdb clears before every
 * query, tells them apart.
 *
 * A repository calls this right after a read whose "no rows" answer a
 * caller acts on (a settlement that would find no Reservations to pay
 * Ticket Back on, an Expense that would look missing), so a failed read
 * throws instead of passing for an empty result. A read that succeeds
 * with no rows leaves last_error empty and is still returned as empty /
 * null. Throwing inside a transaction rolls it back
 * (TransactionManagerInterface); outside one, the request fails with a
 * server error and nothing is written.
 */
final class WpdbReadGuard
{
    public static function throwIfFailed(wpdb $wpdb, string $message): void
    {
        $error = (string) ($wpdb->last_error ?? '');

        if ($error !== '') {
            throw new RuntimeException($message . ' ' . $error);
        }
    }
}
