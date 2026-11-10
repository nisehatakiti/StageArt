<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Production\ProductionId;

/**
 * PR #7 review (R4, R5): the Production lock as the Modules take it, at the
 * start of their transaction and before any Performance / Ticket /
 * Reservation row lock - the lock order is Production -> Performance ->
 * Reservation -> writes (ProductionLifecycle.md), so no two operations
 * ever wait for each other in opposite order.
 *
 * Booking and reception operations hold a shared lock on the Production
 * row until their transaction ends: they still run side by side, but
 * Production Completion, settlements and the settings changes they depend
 * on (Ticket price / status, Ticket Back, Production-wide capacity), which
 * hold the exclusive lock, wait for the operations already running and
 * make new ones wait until they commit. Read under the lock, the
 * Production's status is the committed one.
 *
 * Every method throws
 * - \StageArt\Application\Production\ProductionNotFoundException when there is no such Production,
 * - \RuntimeException when the lock cannot be taken (inside a
 *   WordPressTransactionManager run, a lock wait timeout or deadlock
 *   becomes \StageArt\Application\Shared\DatabaseBusyException).
 */
interface ProductionActivityLockContract
{
    /**
     * Shared lock for a new booking or reception operation: booking,
     * walk-up sale, Check-in, no-show, guest count / attribution change,
     * self-service change.
     *
     * @throws \StageArt\Application\Production\ProductionClosedException when it is CANCELLED, COMPLETED or ARCHIVED
     */
    public function lockOpenProduction(ProductionId $productionId): void;

    /**
     * Shared lock for an operation that only takes a booking back
     * (self-service cancellation, Check-in reversal / refund) or cancels a
     * Performance: still allowed on a CANCELLED Production, whose bookings
     * have to be wound down, but not once the settlement is closed.
     *
     * @throws \StageArt\Application\Production\ProductionClosedException when it is COMPLETED or ARCHIVED
     */
    public function lockUnsettledProduction(ProductionId $productionId): void;

    /**
     * (R6) Exclusive lock with the same status rule, for a Performance
     * edit: its capacity is what bookings and walk-up sales check under
     * their shared lock, so a capacity change waits for the bookings in
     * progress and the seats it compares against are final.
     *
     * @throws \StageArt\Application\Production\ProductionClosedException when it is COMPLETED or ARCHIVED
     */
    public function lockUnsettledProductionExclusively(ProductionId $productionId): void;

    /**
     * Exclusive lock for a setting that bookings and reception read under
     * their shared lock (a Ticket's price / status). Whatever the status.
     */
    public function lockProductionExclusively(ProductionId $productionId): void;
}
