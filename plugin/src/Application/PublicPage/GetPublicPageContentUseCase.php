<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\ContentLayoutRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageContentRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/**
 * The コンテンツ tab of the Organization edit screen: its layout, its
 * 自由コンテンツ and the Productions and members it can place. Only for the people who
 * may edit the Organization public page (PublicPageAuthorization).
 */
final class GetPublicPageContentUseCase
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

    public function execute(string $organizationId, int $wordPressUserId): PublicPageContentAdminResult
    {
        $site = PublicPageSite::organization();
        $this->authorization->assertCanEdit($site, $organizationId, $wordPressUserId);

        return new PublicPageContentAdminResult(
            $this->layouts->find($site, $organizationId),
            $this->contents->findByOwner($site, $organizationId),
            $this->productions->forOrganization($organizationId),
            array_values($this->members->forOrganization($organizationId))
        );
    }
}
