<?php

declare(strict_types=1);

namespace StageArt\Application\Favorite;

final class AddFavoriteCommand
{
    public int $requestedByWordPressUserId;
    public string $targetType;
    public string $targetId;

    public function __construct(int $requestedByWordPressUserId, string $targetType, string $targetId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
    }
}
