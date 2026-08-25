<?php

declare(strict_types=1);

namespace StageArt\Domain\Favorite;

use StageArt\Domain\Person\PersonId;

interface FavoriteRepositoryInterface
{
    public function save(Favorite $favorite): void;

    public function delete(FavoriteId $id): void;

    public function findByPersonAndTarget(PersonId $personId, string $targetType, string $targetId): ?Favorite;

    /**
     * @return Favorite[]
     */
    public function findByPersonId(PersonId $personId): array;
}
