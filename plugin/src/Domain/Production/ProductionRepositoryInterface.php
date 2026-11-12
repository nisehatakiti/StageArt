<?php

declare(strict_types=1);

namespace StageArt\Domain\Production;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Project\ProjectId;

interface ProductionRepositoryInterface
{
    public function save(Production $production): void;

    public function findById(ProductionId $id): ?Production;

    /**
     * PR #7 review (R4), the Production lock: findById() with an exclusive
     * row lock held until the caller's transaction ends (SELECT ... FOR
     * UPDATE). Taken first, inside the transaction, by the operations that
     * close or settle the Production's accounts - Production Completion,
     * settling a member and cancelling a settlement - so they run one at a
     * time and never alongside a booking or a reception operation (which
     * hold findByIdForShare()). A locking read sees the latest committed
     * row. Throws when the read fails; null only when there is no such
     * Production.
     */
    public function findByIdForUpdate(ProductionId $id): ?Production;

    /**
     * The shared form of the Production lock (SELECT ... LOCK IN SHARE
     * MODE): many bookings / reception operations of one Production hold
     * it at once, but none while findByIdForUpdate() is held, so a
     * completion waits for them and they wait for it. Taken first inside
     * the transaction, before the Performance and Reservation row locks
     * (lock order: Production -> Performance -> Reservation). Throws when
     * the read fails; null only when there is no such Production.
     */
    public function findByIdForShare(ProductionId $id): ?Production;

    public function findBySlug(string $slug): ?Production;

    /**
     * @param ProductionId[] $ids
     * @return Production[]
     */
    public function findByIds(array $ids): array;

    /**
     * @param ProjectId[] $projectIds
     * @return Production[]
     */
    public function findByProjectIds(array $projectIds): array;

    /**
     * @return Production[]
     */
    public function findByPrimaryManagerPersonId(PersonId $personId): array;

    /** StageArt Web β版's public Production search (公演・活動検索) -
     * matches on `name` only, restricted to published Productions only.
     *
     * @return Production[]
     */
    public function searchPublished(string $query, int $limit): array;
}
