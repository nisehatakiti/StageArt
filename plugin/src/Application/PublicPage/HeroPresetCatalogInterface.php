<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\PublicPageSite;

/**
 * The hero background presets of each page, read from StageArtCore's
 * manifests (`assets/hero/organization/manifest.json`,
 * `assets/hero/production/manifest.json`), never a list of its own.
 */
interface HeroPresetCatalogInterface
{
    /**
     * @return list<array{id: string, label: string, category: string, image_url: string, thumbnail_url: string}>
     *         in manifest order
     */
    public function presets(PublicPageSite $site): array;
}
