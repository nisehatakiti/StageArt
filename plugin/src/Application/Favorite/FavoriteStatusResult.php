<?php

declare(strict_types=1);

namespace StageArt\Application\Favorite;

final class FavoriteStatusResult
{
    public string $targetType;
    public string $targetId;
    public bool $isFavorited;

    public function __construct(string $targetType, string $targetId, bool $isFavorited)
    {
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->isFavorited = $isFavorited;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'is_favorited' => $this->isFavorited,
        ];
    }
}
