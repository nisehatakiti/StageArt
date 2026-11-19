<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\PublicPage\HeroPresetCatalogInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/** A few ids taken from StageArtCore's real manifests (organization / production). */
final class FakeHeroPresetCatalog implements HeroPresetCatalogInterface
{
    public function presets(PublicPageSite $site): array
    {
        $ids = $site->isProduction()
            ? ['sky-blue' => 'production-hero-01-sky.jpg', 'sea-blue' => 'production-hero-05-blue-sea.jpg']
            : ['circle-shadows' => 'circle-shadows.jpg', 'confetti' => 'confetti.jpg'];
        $out = [];

        foreach ($ids as $id => $file) {
            $out[] = [
                'id' => $id,
                'label' => $id,
                'category' => 'test',
                'image_url' => "https://api.example.test/assets/hero/{$site->toString()}/{$file}",
                'thumbnail_url' => "https://api.example.test/assets/hero/{$site->toString()}/thumbs/{$file}",
            ];
        }

        return $out;
    }
}
