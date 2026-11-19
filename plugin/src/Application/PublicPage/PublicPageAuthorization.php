<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;
use StageArt\Domain\Role\RoleKey;

/**
 * Who may change a public page's display settings - the same people who
 * may already edit that page's content:
 * - Organization: OWNER / ORGANIZATION_MANAGER (as UpdateOrganizationUseCase
 *   and UpdateOrganizationLogoUseCase).
 * - Production: the PrimaryManager (ProductionAuthorizationService::
 *   canManageProduction, as UpdateProductionUseCase).
 */
final class PublicPageAuthorization
{
    private OrganizationRepositoryInterface $organizations;
    private ProductionRepositoryInterface $productions;
    private OrganizationAuthorizationService $organizationAuthorization;
    private ProductionAuthorizationService $productionAuthorization;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        ProductionRepositoryInterface $productions,
        OrganizationAuthorizationService $organizationAuthorization,
        ProductionAuthorizationService $productionAuthorization
    ) {
        $this->organizations = $organizations;
        $this->productions = $productions;
        $this->organizationAuthorization = $organizationAuthorization;
        $this->productionAuthorization = $productionAuthorization;
    }

    /**
     * @throws PublicPageAccessDeniedException
     * @throws PublicPageOwnerNotFoundException
     * @throws \InvalidArgumentException for a malformed id
     */
    public function assertCanEdit(PublicPageSite $site, string $ownerId, int $wordPressUserId): void
    {
        $person = $this->organizationAuthorization->resolveCurrentPerson($wordPressUserId);

        if (! $person) {
            throw new PublicPageAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        if ($site->isProduction()) {
            $production = $this->productions->findById(ProductionId::fromString($ownerId));

            if (! $production) {
                throw new PublicPageOwnerNotFoundException("Production not found: {$ownerId}");
            }

            if (! $this->productionAuthorization->canManageProduction($person, $production)) {
                throw new PublicPageAccessDeniedException('Only the PrimaryManager can change this Production public page.');
            }

            return;
        }

        $organizationId = OrganizationId::fromString($ownerId);

        if (! $this->organizationAuthorization->hasRole($person, $organizationId, [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new PublicPageAccessDeniedException('Only an Organization Owner or delegate can change this Organization public page.');
        }

        if (! $this->organizations->findById($organizationId)) {
            throw new PublicPageOwnerNotFoundException("Organization not found: {$ownerId}");
        }
    }
}
