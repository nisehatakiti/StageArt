<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\PublicPageSettings;
use StageArt\Domain\PublicPage\ThemePresets;

/**
 * What the 公開ページ設定 tab needs: the saved settings plus the choices
 * it offers (theme presets, fonts and hero presets of this page), so the
 * app never keeps a second copy of those lists.
 */
final class PublicPageSettingsResult
{
    private PublicPageSettings $settings;
    /** @var list<array<string, string>> */
    private array $heroPresets;

    /** @param list<array<string, string>> $heroPresets */
    public function __construct(PublicPageSettings $settings, array $heroPresets)
    {
        $this->settings = $settings;
        $this->heroPresets = $heroPresets;
    }

    public function settings(): PublicPageSettings
    {
        return $this->settings;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $site = $this->settings->site();
        $themePresets = [];

        foreach (ThemePresets::presets($site) as $key => $preset) {
            $themePresets[] = ['key' => $key] + $preset;
        }

        $fonts = [];

        foreach (ThemePresets::FONTS as $font) {
            $fonts[] = ['key' => $font, 'label' => ThemePresets::FONT_LABELS[$font]];
        }

        return [
            'site' => $site->toString(),
            'theme' => $this->settings->theme()->toArray(),
            'hero' => $this->settings->hero()->toArray(),
            'theme_presets' => $themePresets,
            'fonts' => $fonts,
            'hero_presets' => $this->heroPresets,
        ];
    }
}
