<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use RuntimeException;
use StageArt\Application\Rehearsal\RehearsalCreateRequestDuplicateException;
use StageArt\Application\Rehearsal\RehearsalCreateRequestRecord;
use StageArt\Application\Rehearsal\RehearsalCreateRequestStoreInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalId;

/**
 * PR #7 R12: UNIQUE (production_id, idempotency_key) in memory, with
 * snapshot()/restore() for RollbackingTransactionManager. beforeRecord()
 * runs a callback at the moment record() is called - a test uses it to
 * let a concurrent request commit the same key first, as the DB does when
 * the second INSERT waits on the first transaction's UNIQUE key.
 * failNextRecord() makes record() fail like a non-duplicate DB error.
 */
final class InMemoryRehearsalCreateRequestStore implements RehearsalCreateRequestStoreInterface
{
    /** @var array<string, RehearsalCreateRequestRecord> */
    private array $records = [];
    /**
     * Records committed by "another request" (seed()): a rollback of this
     * request's transaction does not remove them, as in the DB.
     *
     * @var array<string, RehearsalCreateRequestRecord>
     */
    private array $committedElsewhere = [];
    /** @var callable|null */
    private $beforeRecord = null;
    private bool $failNextRecord = false;

    public function find(ProductionId $productionId, string $idempotencyKey): ?RehearsalCreateRequestRecord
    {
        return $this->records[self::key($productionId, $idempotencyKey)] ?? null;
    }

    public function record(
        ProductionId $productionId,
        string $idempotencyKey,
        PersonId $requestedBy,
        string $requestHash,
        RehearsalId $rehearsalId
    ): void {
        if ($this->beforeRecord !== null) {
            $callback = $this->beforeRecord;
            $this->beforeRecord = null;
            $callback();
        }

        if ($this->failNextRecord) {
            $this->failNextRecord = false;
            throw new RuntimeException('Simulated database error on the idempotency key table.');
        }

        $key = self::key($productionId, $idempotencyKey);

        if (isset($this->records[$key])) {
            throw new RehearsalCreateRequestDuplicateException('Duplicate entry (simulated UNIQUE constraint).');
        }

        $this->records[$key] = new RehearsalCreateRequestRecord($requestedBy, $requestHash, $rehearsalId);
    }

    /** Stores a record directly, as a request that already committed. */
    public function seed(ProductionId $productionId, string $idempotencyKey, RehearsalCreateRequestRecord $record): void
    {
        $key = self::key($productionId, $idempotencyKey);
        $this->records[$key] = $record;
        $this->committedElsewhere[$key] = $record;
    }

    public function beforeRecord(callable $callback): void
    {
        $this->beforeRecord = $callback;
    }

    public function failNextRecord(): void
    {
        $this->failNextRecord = true;
    }

    public function count(): int
    {
        return count($this->records);
    }

    /** @return array<string, RehearsalCreateRequestRecord> */
    public function snapshot(): array
    {
        return $this->records;
    }

    /** @param array<string, RehearsalCreateRequestRecord> $snapshot */
    public function restore(array $snapshot): void
    {
        $this->records = $snapshot + $this->committedElsewhere;
    }

    private static function key(ProductionId $productionId, string $idempotencyKey): string
    {
        return $productionId->toString() . '|' . $idempotencyKey;
    }
}
