<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\HeroSettings;
use StageArt\Domain\PublicPage\PublicPageSettingsRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/**
 * The look of a public page for its public (unauthenticated) response. No
 * permission or publication check of its own: it is only called by the
 * public by-slug endpoints after their own publication gate has passed
 * (GetPublicOrganizationBySlugUseCase / GetPublicProductionBySlugUseCase).
 *
 * A page never configured has no appearance (null): the public response
 * then carries no public_page at all and the app keeps its own, earlier
 * look for that page. Nothing is saved for such a page. Whether a page is
 * configured is decided per site + owner (an Organization's settings
 * never make its Productions configured, and vice versa).
 */
final class GetPublicPageAppearanceUseCase
{
    private PublicPageSettingsRepositoryInterface $settings;
    private HeroPresetCatalogInterface $heroPresets;

    public function __construct(PublicPageSettingsRepositoryInterface $settings, HeroPresetCatalogInterface $heroPresets)
    {
        $this->settings = $settings;
        $this->heroPresets = $heroPresets;
    }

    public function execute(PublicPageSite $site, string $ownerId): ?PublicPageAppearanceResult
    {
        $settings = $this->settings->find($site, $ownerId);

        if ($settings === null) {
            return null;
        }

        $hero = $settings->hero();
        $imageUrl = $hero->isEnabled() ? $this->heroImageUrl($site, $hero) : null;

        return new PublicPageAppearanceResult(
            $settings->theme()->resolve($site),
            $imageUrl === null ? null : [
                'image_url' => $imageUrl,
                'title' => $hero->title(),
                'description' => $hero->description(),
            ]
        );
    }

    private function heroImageUrl(PublicPageSite $site, HeroSettings $hero): ?string
    {
        if ($hero->type() === HeroSettings::TYPE_CUSTOM) {
            return $hero->customImageUrl();
        }

        foreach ($this->heroPresets->presets($site) as $preset) {
            if ($preset['id'] === $hero->preset()) {
                return $preset['image_url'];
            }
        }

        return null;
    }
}
