<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationSNSRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * The 団体情報 management screen's own list - every registered SNS
 * (visible and hidden), unlike the Public Page path (see
 * GetPublicOrganizationBySlugUseCase), which only ever shows visible
 * ones to the public.
 */
final class ListOrganizationSNSUseCase
{
    private OrganizationSNSRepositoryInterface $sns;
    private OrganizationAuthorizationService $authorization;

    public function __construct(OrganizationSNSRepositoryInterface $sns, OrganizationAuthorizationService $authorization)
    {
        $this->sns = $sns;
        $this->authorization = $authorization;
    }

    /**
     * @return OrganizationSNSResult[]
     */
    public function execute(ListOrganizationSNSQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);
        $organizationId = OrganizationId::fromString($query->organizationId);

        if (! $person || ! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can view SNS link management.');
        }

        return array_map(
            static fn ($sns) => OrganizationSNSResult::fromDomain($sns),
            $this->sns->findByOrganizationId($organizationId)
        );
    }
}
