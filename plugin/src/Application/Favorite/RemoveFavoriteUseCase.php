<?php

declare(strict_types=1);

namespace StageArt\Application\Favorite;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Favorite\FavoriteRepositoryInterface;

/** Idempotent, mirroring UnfollowOrganizationUseCase - removing a
 * Favorite that was never added (or already removed) is still a
 * no-op success. */
final class RemoveFavoriteUseCase
{
    private FavoriteRepositoryInterface $favorites;
    private OrganizationAuthorizationService $authorization;

    public function __construct(FavoriteRepositoryInterface $favorites, OrganizationAuthorizationService $authorization)
    {
        $this->favorites = $favorites;
        $this->authorization = $authorization;
    }

    public function execute(RemoveFavoriteCommand $command): FavoriteStatusResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new FavoriteAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $existing = $this->favorites->findByPersonAndTarget($person->id(), $command->targetType, $command->targetId);

        if ($existing) {
            $this->favorites->delete($existing->id());
        }

        return new FavoriteStatusResult($command->targetType, $command->targetId, false);
    }
}
