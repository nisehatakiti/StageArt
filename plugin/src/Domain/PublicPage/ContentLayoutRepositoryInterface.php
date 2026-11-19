<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

interface ContentLayoutRepositoryInterface
{
    /** null when this page's content layout was never saved. */
    public function find(PublicPageSite $site, string $ownerId): ?ContentLayout;

    public function save(PublicPageSite $site, string $ownerId, ContentLayout $layout): void;
}
