<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalId;

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §26): which Rehearsal a
 * client's create request (idempotency key) produced. A key is unique per
 * Production - the scope the create endpoint and its permission check
 * already work in - backed by a DB UNIQUE constraint, so two concurrent
 * requests with the same key can never both create a Rehearsal.
 *
 * Purely technical, like WalkUpIdempotencyStoreInterface: no business rule
 * depends on it, so it lives in Application, not Domain.
 */
interface RehearsalCreateRequestStoreInterface
{
    public function find(ProductionId $productionId, string $idempotencyKey): ?RehearsalCreateRequestRecord;

    /**
     * Must be called inside the TransactionManagerInterface::run() block
     * that creates the Rehearsal, before anything else is written there,
     * so a rollback removes both and a concurrent request with the same
     * key waits on the UNIQUE key and then fails here.
     *
     * @throws RehearsalCreateRequestDuplicateException when the key is already recorded
     */
    public function record(
        ProductionId $productionId,
        string $idempotencyKey,
        PersonId $requestedBy,
        string $requestHash,
        RehearsalId $rehearsalId
    ): void;
}
