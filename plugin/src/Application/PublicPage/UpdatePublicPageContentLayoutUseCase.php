<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\ContentLayout;
use StageArt\Domain\PublicPage\ContentLayoutRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageContentRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/**
 * Saves where the Organization public page's contents go (Core
 * SiteStructureAdmin::saveHomepage()). The whole layout is replaced; a
 * Production must be one of this Organization's and a 自由コンテンツ one
 * of this page's own and a member an ACTIVE member listed in メンバー管理
 * (OrganizationPublicMembersLookup) - anything else is rejected (422) and
 * nothing is saved. Only this page's layout row is written.
 */
final class UpdatePublicPageContentLayoutUseCase
{
    private ContentLayoutRepositoryInterface $layouts;
    private PublicPageContentRepositoryInterface $contents;
    private OrganizationProductionsLookup $productions;
    private OrganizationPublicMembersLookup $members;
    private PublicPageAuthorization $authorization;

    public function __construct(
        ContentLayoutRepositoryInterface $layouts,
        PublicPageContentRepositoryInterface $contents,
        OrganizationProductionsLookup $productions,
        OrganizationPublicMembersLookup $members,
        PublicPageAuthorization $authorization
    ) {
        $this->layouts = $layouts;
        $this->contents = $contents;
        $this->productions = $productions;
        $this->members = $members;
        $this->authorization = $authorization;
    }

    /** @param mixed $blocks */
    public function execute(string $organizationId, int $wordPressUserId, $blocks): PublicPageContentAdminResult
    {
        $site = PublicPageSite::organization();
        $this->authorization->assertCanEdit($site, $organizationId, $wordPressUserId);

        $productions = $this->productions->forOrganization($organizationId);
        $productionIds = array_map(static fn ($production): string => $production->id()->toString(), $productions);
        $contents = $this->contents->findByOwner($site, $organizationId);
        $contentIds = array_map(static fn ($content): string => $content->id(), $contents);
        $members = $this->members->forOrganization($organizationId);

        $layout = ContentLayout::fromInput(
            $blocks,
            static fn (string $id): bool => in_array($id, $productionIds, true),
            static fn (string $id): bool => in_array($id, $contentIds, true),
            static fn (string $id): bool => isset($members[$id])
        );

        $this->layouts->save($site, $organizationId, $layout);

        return new PublicPageContentAdminResult($layout, $contents, $productions, array_values($members));
    }
}
