<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\PublicPageContent;
use StageArt\Domain\PublicPage\PublicPageContentRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/**
 * Adds a 自由コンテンツ to the Organization public page, or changes one of
 * its own (Core FreeContentAdmin::save()). "draft" hides it from the
 * public page without taking it out of the layout; "publish" shows it
 * wherever it is placed.
 */
final class SavePublicPageContentUseCase
{
    private PublicPageContentRepositoryInterface $contents;
    private PublicPageAuthorization $authorization;

    public function __construct(PublicPageContentRepositoryInterface $contents, PublicPageAuthorization $authorization)
    {
        $this->contents = $contents;
        $this->authorization = $authorization;
    }

    /**
     * @throws PublicPageContentNotFoundException when $contentId is not one of this page's
     * @throws \InvalidArgumentException for an invalid title / body / status
     */
    public function execute(string $organizationId, int $wordPressUserId, ?string $contentId, string $title, string $body, string $status): array
    {
        $site = PublicPageSite::organization();
        $this->authorization->assertCanEdit($site, $organizationId, $wordPressUserId);

        if ($contentId === null) {
            $content = PublicPageContent::create($site, $organizationId, $title, $body, $status);
        } else {
            $current = $this->contents->findById($contentId);

            if ($current === null || ! $current->belongsTo($site, $organizationId)) {
                throw new PublicPageContentNotFoundException("Content not found: {$contentId}");
            }

            $content = $current->edit($title, $body, $status);
        }

        $this->contents->save($content);

        return PublicPageContentAdminResult::content($content);
    }
}
