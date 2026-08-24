<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use RuntimeException;

final class OrganizationSlugAlreadyTakenException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("Organization slug already taken: {$slug}");
    }
}
