<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

interface PublicPageSettingsRepositoryInterface
{
    /** null when this page's settings were never saved. */
    public function find(PublicPageSite $site, string $ownerId): ?PublicPageSettings;

    public function save(PublicPageSettings $settings): void;
}
