<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\PublicPage\PublicPageSettings;
use StageArt\Domain\PublicPage\PublicPageSettingsRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

final class InMemoryPublicPageSettingsRepository implements PublicPageSettingsRepositoryInterface
{
    /** @var array<string, PublicPageSettings> */
    private array $settings = [];

    public int $saveCount = 0;

    public function find(PublicPageSite $site, string $ownerId): ?PublicPageSettings
    {
        return $this->settings[$site->toString() . ':' . $ownerId] ?? null;
    }

    public function save(PublicPageSettings $settings): void
    {
        $this->saveCount++;
        $this->settings[$settings->site()->toString() . ':' . $settings->ownerId()] = $settings;
    }
}
