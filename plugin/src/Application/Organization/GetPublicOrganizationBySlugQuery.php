<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class GetPublicOrganizationBySlugQuery
{
    public string $slug;

    public function __construct(string $slug)
    {
        $this->slug = $slug;
    }
}
