<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\PublicPageContentRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/**
 * Deletes one of the Organization public page's own 自由コンテンツ. A
 * layout slot that placed it is left as saved and shows nothing from then
 * on (Core: a deleted free content renders nothing where it was placed);
 * the editor shows it as an empty column.
 */
final class DeletePublicPageContentUseCase
{
    private PublicPageContentRepositoryInterface $contents;
    private PublicPageAuthorization $authorization;

    public function __construct(PublicPageContentRepositoryInterface $contents, PublicPageAuthorization $authorization)
    {
        $this->contents = $contents;
        $this->authorization = $authorization;
    }

    /** @throws PublicPageContentNotFoundException when it is not one of this page's */
    public function execute(string $organizationId, int $wordPressUserId, string $contentId): void
    {
        $site = PublicPageSite::organization();
        $this->authorization->assertCanEdit($site, $organizationId, $wordPressUserId);

        $content = $this->contents->findById($contentId);

        if ($content === null || ! $content->belongsTo($site, $organizationId)) {
            throw new PublicPageContentNotFoundException("Content not found: {$contentId}");
        }

        $this->contents->delete($contentId);
    }
}
