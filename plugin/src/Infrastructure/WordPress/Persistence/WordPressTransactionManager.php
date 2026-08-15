<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use StageArt\Application\Shared\TransactionManagerInterface;
use Throwable;
use wpdb;

/**
 * WordPress core has no transaction API of its own; raw START
 * TRANSACTION/COMMIT/ROLLBACK via $wpdb->query() is the standard
 * approach WordPress plugins use. Requires the storage engine to
 * support transactions (InnoDB, the modern WordPress default).
 */
final class WordPressTransactionManager implements TransactionManagerInterface
{
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function run(callable $operation)
    {
        $this->wpdb->query('START TRANSACTION');

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            $this->wpdb->query('ROLLBACK');

            throw $exception;
        }

        $this->wpdb->query('COMMIT');

        return $result;
    }
}
