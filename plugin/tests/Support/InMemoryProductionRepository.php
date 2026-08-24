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
}
