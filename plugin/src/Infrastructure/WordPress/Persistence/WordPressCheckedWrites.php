<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use StageArt\Application\Shared\PersistenceException;
use StageArt\Application\Shared\UniqueConstraintViolationException;
use Throwable;
use wpdb;

/**
 * PR #7 R14-B (design doc §7.4, N2): `$wpdb` reports a failed write only by
 * returning false. The Repositories R14-B changes or adds write through
 * here, so no failure is ignored:
 *
 * - insert() / update() returning false throws: a UNIQUE key violation
 *   (MySQL 1062) as UniqueConstraintViolationException, anything else as
 *   PersistenceException.
 * - update() returning 0 is not a failure (no row changed, e.g. the same
 *   values again); the count is returned for callers that update
 *   conditionally and must judge 0 themselves.
 * - existingId() tells "no such row" from a failed read, so a save never
 *   turns a read failure into an INSERT.
 *
 * 1062 is read from the connection's error number (mysqli_errno); only
 * when that is not available (no mysqli handle) does the "Duplicate entry"
 * prefix of MySQL's message decide, as WordPressRehearsalCreateRequestStore
 * does. The exception never carries the database's error text.
 *
 * Other Repositories are not changed here (N2: a separate cross-cutting
 * audit).
 */
final class WordPressCheckedWrites
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function insert(wpdb $wpdb, string $table, array $row): void
    {
        if ($wpdb->insert($table, $row) === false) {
            throw self::failure($wpdb, 'insert into', $table);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $where
     * @return int the number of rows changed (0 is not a failure)
     */
    public static function update(wpdb $wpdb, string $table, array $row, array $where): int
    {
        $result = $wpdb->update($table, $row, $where);

        if ($result === false) {
            throw self::failure($wpdb, 'update', $table);
        }

        return (int) $result;
    }

    /**
     * @param array<string, mixed> $where
     * @return int the number of rows deleted (0 is not a failure)
     */
    public static function delete(wpdb $wpdb, string $table, array $where): int
    {
        $result = $wpdb->delete($table, $where);

        if ($result === false) {
            throw self::failure($wpdb, 'delete from', $table);
        }

        return (int) $result;
    }

    /**
     * R14-B stage 3: a prepared UPDATE / DELETE statement run through
     * $wpdb->query() (e.g. a conditional UPDATE ... WHERE ... IS NULL, or an
     * atomic counter). false throws like insert() / update(); otherwise the
     * number of rows affected is returned and the caller judges what 0
     * means (design doc §7.4).
     */
    public static function query(wpdb $wpdb, string $operation, string $table, string $preparedSql): int
    {
        $result = $wpdb->query($preparedSql);

        if ($result === false) {
            throw self::failure($wpdb, $operation, $table);
        }

        return (int) $result;
    }

    /**
     * R14-B stage 3: called right after a read whose "no rows" answer the
     * caller acts on (a locking read, a candidate search): $wpdb answers a
     * failed read with no rows, so a non-empty last_error throws instead.
     * The caller clears last_error before the read. Like the writes, the
     * exception never carries the database's error text.
     */
    public static function assertRead(wpdb $wpdb, string $table): void
    {
        if ((string) ($wpdb->last_error ?? '') !== '') {
            throw new PersistenceException('read from', $table, self::errno($wpdb));
        }
    }

    /**
     * The id when a row with this id exists, null when none does; a failed
     * read throws instead of reading as "none".
     */
    public static function existingId(wpdb $wpdb, string $table, string $id): ?string
    {
        $wpdb->last_error = '';
        $found = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %s", $id));

        if ($found === null && (string) $wpdb->last_error !== '') {
            throw new PersistenceException('read from', $table, self::errno($wpdb));
        }

        return $found !== null ? (string) $found : null;
    }

    private static function failure(wpdb $wpdb, string $operation, string $table): PersistenceException
    {
        $errno = self::errno($wpdb);

        if ($errno === UniqueConstraintViolationException::MYSQL_DUPLICATE_ENTRY
            || ($errno === null && stripos((string) $wpdb->last_error, 'Duplicate entry') === 0)) {
            return new UniqueConstraintViolationException($operation, $table);
        }

        return new PersistenceException($operation, $table, $errno);
    }

    /**
     * The connection's last MySQL error number, or null when it cannot be
     * read (no mysqli connection, a closed handle, or 0).
     */
    private static function errno(wpdb $wpdb): ?int
    {
        $handle = $wpdb->dbh;

        if (! $handle instanceof \mysqli) {
            return null;
        }

        try {
            $errno = mysqli_errno($handle);
        } catch (Throwable $exception) {
            return null;
        }

        return is_int($errno) && $errno > 0 ? $errno : null;
    }
}
