<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use RuntimeException;
use StageArt\Application\Rehearsal\RehearsalCreateRequestDuplicateException;
use StageArt\Application\Rehearsal\RehearsalCreateRequestRecord;
use StageArt\Application\Rehearsal\RehearsalCreateRequestStoreInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalId;
use wpdb;

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §26):
 * stageart_rehearsal_create_requests, UNIQUE (production_id,
 * idempotency_key). Unlike the walk-up store, only a duplicate-key error
 * (MySQL 1062) means "another request recorded this key first"; any other
 * failed write or read throws, so a database error is never mistaken for
 * a duplicate or for "no such key".
 */
final class WordPressRehearsalCreateRequestStore implements RehearsalCreateRequestStoreInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_rehearsal_create_requests';
    }

    public function find(ProductionId $productionId, string $idempotencyKey): ?RehearsalCreateRequestRecord
    {
        $this->wpdb->last_error = '';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT requested_by, request_hash, rehearsal_id FROM {$this->table} WHERE production_id = %s AND idempotency_key = %s",
                $productionId->toString(),
                $idempotencyKey
            ),
            ARRAY_A
        );

        if ($row === null && $this->wpdb->last_error !== '') {
            throw new RuntimeException('Could not read the Rehearsal create request: ' . $this->wpdb->last_error);
        }

        if (! is_array($row)) {
            return null;
        }

        return new RehearsalCreateRequestRecord(
            PersonId::fromString($row['requested_by']),
            $row['request_hash'],
            RehearsalId::fromString($row['rehearsal_id'])
        );
    }

    public function record(
        ProductionId $productionId,
        string $idempotencyKey,
        PersonId $requestedBy,
        string $requestHash,
        RehearsalId $rehearsalId
    ): void {
        $result = $this->wpdb->insert($this->table, [
            'production_id' => $productionId->toString(),
            'idempotency_key' => $idempotencyKey,
            'requested_by' => $requestedBy->toString(),
            'request_hash' => $requestHash,
            'rehearsal_id' => $rehearsalId->toString(),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($result !== false) {
            return;
        }

        if (stripos($this->wpdb->last_error, 'Duplicate entry') !== false) {
            throw new RehearsalCreateRequestDuplicateException('This idempotency key was recorded by another request.');
        }

        throw new RuntimeException('Could not record the Rehearsal create request: ' . $this->wpdb->last_error);
    }
}
