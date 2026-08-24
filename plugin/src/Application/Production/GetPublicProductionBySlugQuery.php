<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

final class GetPublicProductionBySlugQuery
{
    public string $slug;

    public function __construct(string $slug)
    {
        $this->slug = $slug;
    }
}
