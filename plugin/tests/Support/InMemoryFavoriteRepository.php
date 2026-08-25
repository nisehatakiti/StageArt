<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Favorite\Favorite;
use StageArt\Domain\Favorite\FavoriteId;
use StageArt\Domain\Favorite\FavoriteRepositoryInterface;
use StageArt\Domain\Person\PersonId;

final class InMemoryFavoriteRepository implements FavoriteRepositoryInterface
{
    /** @var array<string, Favorite> */
    private array $favorites = [];

    public function save(Favorite $favorite): void
    {
        $this->favorites[$favorite->id()->toString()] = $favorite;
    }

    public function delete(FavoriteId $id): void
    {
        unset($this->favorites[$id->toString()]);
    }

    public function findByPersonAndTarget(PersonId $personId, string $targetType, string $targetId): ?Favorite
    {
        foreach ($this->favorites as $favorite) {
            if ($favorite->personId()->equals($personId) && $favorite->targetType() === $targetType && $favorite->targetId() === $targetId) {
                return $favorite;
            }
        }

        return null;
    }

    public function findByPersonId(PersonId $personId): array
    {
        return array_values(array_filter(
            $this->favorites,
            static fn (Favorite $favorite): bool => $favorite->personId()->equals($personId)
        ));
    }
}
