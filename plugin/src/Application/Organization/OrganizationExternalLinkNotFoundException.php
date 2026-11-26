<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use RuntimeException;

final class OrganizationExternalLinkNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("OrganizationExternalLink not found: {$id}");
    }
}
