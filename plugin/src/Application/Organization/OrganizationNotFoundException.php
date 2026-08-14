<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use RuntimeException;

final class OrganizationNotFoundException extends RuntimeException
{
    public function __construct(string $organizationId)
    {
        parent::__construct("Organization not found: {$organizationId}");
    }
}
