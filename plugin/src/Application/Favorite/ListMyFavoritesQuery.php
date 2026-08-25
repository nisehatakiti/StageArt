<?php

declare(strict_types=1);

namespace StageArt\Application\Favorite;

final class ListMyFavoritesQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
