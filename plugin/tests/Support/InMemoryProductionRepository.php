<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Project\ProjectId;

final class InMemoryProductionRepository implements ProductionRepositoryInterface
{
    /** @var array<string, Production> */
    private array $productions = [];

    public function save(Production $production): void
    {
        $this->productions[$production->id()->toString()] = $production;
    }

    public function findById(ProductionId $id): ?Production
    {
        return $this->productions[$id->toString()] ?? null;
    }

    /** @var array<int, array{0: string, 1: string}> every Production lock taken: [mode, ProductionId] */
    public array $locks = [];

    /**
     * Runs right before a lock is granted - the moment a waiting request
     * proceeds after the other one committed - so a test can play that
     * other request's committed change there. Receives the lock mode.
     *
     * @var (callable(string): void)|null
     */
    public $beforeLockGranted = null;

    /** When set, every lock fails with it (a lock wait timeout, a deadlock). */
    public ?\Throwable $lockFailure = null;

    public function findByIdForUpdate(ProductionId $id): ?Production
    {
        return $this->lock('FOR UPDATE', $id);
    }

    public function findByIdForShare(ProductionId $id): ?Production
    {
        return $this->lock('SHARE', $id);
    }

    private function lock(string $mode, ProductionId $id): ?Production
    {
        if ($this->lockFailure !== null) {
            throw $this->lockFailure;
        }

        if ($this->beforeLockGranted !== null) {
            $hook = $this->beforeLockGranted;
            $this->beforeLockGranted = null;
            $hook($mode);
        }

        $this->locks[] = [$mode, $id->toString()];

        return $this->findById($id);
    }

    public function findByIds(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $production = $this->findById($id);

            if ($production !== null) {
                $result[] = $production;
            }
        }

        return $result;
    }

    public function findByProjectIds(array $projectIds): array
    {
        $projectIdStrings = array_map(static fn (ProjectId $id): string => $id->toString(), $projectIds);

        return array_values(array_filter(
            $this->productions,
            static fn (Production $production): bool => in_array(
                $production->projectId()->toString(),
                $projectIdStrings,
                true
            )
        ));
    }

    public function findByPrimaryManagerPersonId(PersonId $personId): array
    {
        return array_values(array_filter(
            $this->productions,
            static fn (Production $production): bool => $production->primaryManagerPersonId()->equals($personId)
        ));
    }

    public function findBySlug(string $slug): ?Production
    {
        foreach ($this->productions as $production) {
            if ($production->slug()?->toString() === $slug) {
                return $production;
            }
        }

        return null;
    }

    public function searchPublished(string $query, int $limit): array
    {
        $matches = array_values(array_filter(
            $this->productions,
            static fn (Production $production): bool => $production->isPublished()
                && mb_stripos($production->name()->toString(), $query) !== false
        ));

        usort($matches, static fn (Production $a, Production $b): int => $a->name()->toString() <=> $b->name()->toString());

        return array_slice($matches, 0, $limit);
    }
}
