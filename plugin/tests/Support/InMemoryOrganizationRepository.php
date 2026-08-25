<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

final class InMemoryOrganizationRepository implements OrganizationRepositoryInterface
{
    /** @var array<string, Organization> */
    private array $organizations = [];

    public function save(Organization $organization): void
    {
        $this->organizations[$organization->id()->toString()] = $organization;
    }

    public function findById(OrganizationId $id): ?Organization
    {
        return $this->organizations[$id->toString()] ?? null;
    }

    public function findByIds(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $organization = $this->findById($id);

            if ($organization !== null) {
                $result[] = $organization;
            }
        }

        return $result;
    }

    public function findBySlug(string $slug): ?Organization
    {
        foreach ($this->organizations as $organization) {
            if ($organization->slug()?->toString() === $slug) {
                return $organization;
            }
        }

        return null;
    }

    public function searchPublished(string $query, int $limit): array
    {
        $matches = array_values(array_filter(
            $this->organizations,
            static fn (Organization $organization): bool => $organization->isPublished()
                && mb_stripos($organization->name()->toString(), $query) !== false
        ));

        usort($matches, static fn (Organization $a, Organization $b): int => $a->name()->toString() <=> $b->name()->toString());

        return array_slice($matches, 0, $limit);
    }
}
