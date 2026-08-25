<?php

declare(strict_types=1);

namespace StageArt\Application\Favorite;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Favorite\Favorite;
use StageArt\Domain\Favorite\FavoriteRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/** Any authenticated Person can Favorite any published Organization or
 * Production - not Membership-gated, mirroring FollowOrganizationUseCase's
 * same "一般観客も" reasoning. Favoriting twice is idempotent (returns the
 * existing row's state rather than erroring), unlike Membership Requests'
 * duplicate-rejection - Favorite has no approval workflow to conflict
 * with. */
final class AddFavoriteUseCase
{
    private FavoriteRepositoryInterface $favorites;
    private OrganizationRepositoryInterface $organizations;
    private ProductionRepositoryInterface $productions;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        FavoriteRepositoryInterface $favorites,
        OrganizationRepositoryInterface $organizations,
        ProductionRepositoryInterface $productions,
        OrganizationAuthorizationService $authorization
    ) {
        $this->favorites = $favorites;
        $this->organizations = $organizations;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(AddFavoriteCommand $command): FavoriteStatusResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new FavoriteAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $this->assertTargetIsPublished($command->targetType, $command->targetId);

        $existing = $this->favorites->findByPersonAndTarget($person->id(), $command->targetType, $command->targetId);

        if (! $existing) {
            $this->favorites->save(Favorite::create($person->id(), $command->targetType, $command->targetId));
        }

        return new FavoriteStatusResult($command->targetType, $command->targetId, true);
    }

    private function assertTargetIsPublished(string $targetType, string $targetId): void
    {
        if ($targetType === Favorite::TARGET_TYPE_ORGANIZATION) {
            $organization = $this->organizations->findById(OrganizationId::fromString($targetId));

            if (! $organization || ! $organization->isPublished()) {
                throw new FavoriteTargetNotFoundException($targetType, $targetId);
            }

            return;
        }

        $production = $this->productions->findById(ProductionId::fromString($targetId));

        if (! $production || ! $production->isPublished()) {
            throw new FavoriteTargetNotFoundException($targetType, $targetId);
        }
    }
}
