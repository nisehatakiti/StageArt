<?php

declare(strict_types=1);

namespace StageArt\Application\Favorite;

use RuntimeException;

final class FavoriteTargetNotFoundException extends RuntimeException
{
    public function __construct(string $targetType, string $targetId)
    {
        parent::__construct("Favorite target not found: {$targetType} {$targetId}");
    }
}
