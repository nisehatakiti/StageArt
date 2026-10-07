<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

interface OrganizationExternalLinkRepositoryInterface
{
    public function save(OrganizationExternalLink $link): void;

    public function findById(OrganizationExternalLinkId $id): ?OrganizationExternalLink;

    /**
     * @return OrganizationExternalLink[] ordered by display_order ascending
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;

    public function delete(OrganizationExternalLinkId $id): void;
}
