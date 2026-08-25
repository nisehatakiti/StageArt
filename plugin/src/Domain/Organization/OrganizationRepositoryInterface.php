<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

interface OrganizationRepositoryInterface
{
    public function save(Organization $organization): void;

    public function findById(OrganizationId $id): ?Organization;

    public function findBySlug(string $slug): ?Organization;

    /**
     * @param OrganizationId[] $ids
     * @return Organization[]
     */
    public function findByIds(array $ids): array;

    /** StageArt Web β版's public Organization search (団体検索) - matches
     * on `name` only (never `type`/`description`, which are not
     * guaranteed public-facing text), restricted to published
     * Organizations only.
     *
     * @return Organization[]
     */
    public function searchPublished(string $query, int $limit): array;
}
