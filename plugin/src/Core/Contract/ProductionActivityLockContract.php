<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Production\ProductionId;

/**
 * PR #7 review (R4): the Production lock as the Reservation and Check-in
 * Modules take it. Every operation that creates a Reservation or changes
 * one (booking, walk-up sale, Check-in, no-show, Check-in reversal, guest
 * count, attribution, self-service change / cancel) calls this first
 * inside its transaction, then takes its Performance / Reservation row
 * locks - the lock order is Production -> Performance -> Reservation, so
 * no two of these operations, and none of them with Production
 * Completion or a settlement, ever wait for each other in opposite order.
 *
 * It holds a shared lock on the Production row until the transaction
 * ends: bookings and reception of one Production still run side by side,
 * but Production Completion and settlements, which hold the exclusive
 * lock (ProductionRepositoryInterface::findByIdForUpdate()), wait for the
 * operations already running and make new ones wait until they commit.
 * Read under the lock, the Production's status is the committed one, so
 * once Production Completion has committed no booking or reception
 * operation goes through, and while it is checking the settlement none
 * can change what it is checking.
 */
interface ProductionActivityLockContract
{
    /**
     * @throws \StageArt\Application\Production\ProductionNotFoundException when there is no such Production
     * @throws \StageArt\Application\Production\ProductionClosedException when it is COMPLETED or ARCHIVED
     * @throws \RuntimeException when the lock cannot be taken (lock wait timeout, deadlock, DB error)
     */
    public function lockOpenProduction(ProductionId $productionId): void;
}
