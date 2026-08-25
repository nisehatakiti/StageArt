<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationRepositoryInterface;

/** Public, unauthenticated search (団体検索) - see
 * OrganizationRepositoryInterface::searchPublished()'s own docblock for
 * why this only ever considers published Organizations. An empty query
 * returns no results rather than "everything", since this Use Case has
 * no pagination and an unbounded browse-all is a different feature. */
final class SearchOrganizationsUseCase
{
    private const LIMIT = 20;

    private OrganizationRepositoryInterface $organizations;

    public function __construct(OrganizationRepositoryInterface $organizations)
    {
        $this->organizations = $organizations;
    }

    /**
     * @return PublicOrganizationResult[]
     */
    public function execute(SearchOrganizationsQuery $query): array
    {
        $trimmed = trim($query->query);

        if ($trimmed === '') {
            return [];
        }

        return array_map(
            static fn ($organization) => PublicOrganizationResult::fromDomain($organization),
            $this->organizations->searchPublished($trimmed, self::LIMIT)
        );
    }
}
