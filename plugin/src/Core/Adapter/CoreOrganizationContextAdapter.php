<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

final class CoreOrganizationContextAdapter implements OrganizationContextContract
{
    private OrganizationRepositoryInterface $organizations;

    public function __construct(OrganizationRepositoryInterface $organizations)
    {
        $this->organizations = $organizations;
    }

    public function organizationExists(OrganizationId $organizationId): bool
    {
        return $this->organizations->findById($organizationId) !== null;
    }
}
