<?php

declare(strict_types=1);

namespace StageArt\Application\Favorite;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Favorite\Favorite;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Project\ProjectRepositoryInterface;

/** docs/09-Home §「お気に入り」一覧 - resolves both Organization and
 * Production Favorites in one list, each with enough identity to link
 * directly to its public page. A target that no longer exists/resolves
 * (e.g. deleted) is silently skipped rather than erroring the whole
 * list, matching ListMyMembershipsUseCase's own precedent. */
final class ListMyFavoritesUseCase
{
    private \StageArt\Domain\Favorite\FavoriteRepositoryInterface $favorites;
    private OrganizationRepositoryInterface $organizations;
    private ProductionRepositoryInterface $productions;
    private ProjectRepositoryInterface $projects;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        \StageArt\Domain\Favorite\FavoriteRepositoryInterface $favorites,
        OrganizationRepositoryInterface $organizations,
        ProductionRepositoryInterface $productions,
        ProjectRepositoryInterface $projects,
        OrganizationAuthorizationService $authorization
    ) {
        $this->favorites = $favorites;
        $this->organizations = $organizations;
        $this->productions = $productions;
        $this->projects = $projects;
        $this->authorization = $authorization;
    }

    /**
     * @return MyFavoriteResult[]
     */
    public function execute(ListMyFavoritesQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new FavoriteAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $favorites = $this->favorites->findByPersonId($person->id());
        $results = [];

        foreach ($favorites as $favorite) {
            $result = $favorite->targetType() === Favorite::TARGET_TYPE_ORGANIZATION
                ? $this->resolveOrganizationFavorite($favorite)
                : $this->resolveProductionFavorite($favorite);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    private function resolveOrganizationFavorite(Favorite $favorite): ?MyFavoriteResult
    {
        $organization = $this->organizations->findById(OrganizationId::fromString($favorite->targetId()));

        if (! $organization) {
            return null;
        }

        return new MyFavoriteResult(
            $favorite->id()->toString(),
            Favorite::TARGET_TYPE_ORGANIZATION,
            $organization->id()->toString(),
            $organization->name()->toString(),
            $organization->slug()?->toString(),
            null,
            $favorite->favoritedAt()->format(DATE_ATOM)
        );
    }

    private function resolveProductionFavorite(Favorite $favorite): ?MyFavoriteResult
    {
        $production = $this->productions->findById(ProductionId::fromString($favorite->targetId()));

        if (! $production) {
            return null;
        }

        $project = $this->projects->findById($production->projectId());
        $organizationSlug = null;

        if ($project) {
            $organization = $this->organizations->findById($project->organizationId());
            $organizationSlug = $organization?->slug()?->toString();
        }

        return new MyFavoriteResult(
            $favorite->id()->toString(),
            Favorite::TARGET_TYPE_PRODUCTION,
            $production->id()->toString(),
            $production->name()->toString(),
            $production->slug()?->toString(),
            $organizationSlug,
            $favorite->favoritedAt()->format(DATE_ATOM)
        );
    }
}
