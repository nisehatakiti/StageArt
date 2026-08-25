<?php

declare(strict_types=1);

namespace StageArt\Domain\Production;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Project\ProjectId;

interface ProductionRepositoryInterface
{
    public function save(Production $production): void;

    public function findById(ProductionId $id): ?Production;

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
