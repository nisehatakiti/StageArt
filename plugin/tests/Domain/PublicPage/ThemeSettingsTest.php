<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\PublicPage;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\PublicPage\PublicPageSite;
use StageArt\Domain\PublicPage\ThemePresets;
use StageArt\Domain\PublicPage\ThemeSettings;

final class ThemeSettingsTest extends TestCase
{
    public function test_preset_lists_match_stageart_core(): void
    {
        $organization = array_keys(ThemePresets::presets(PublicPageSite::organization()));
        $production = array_keys(ThemePresets::presets(PublicPageSite::production()));

        $this->assertSame(
            ['light_standard', 'light_elegant', 'light_pop', 'light_pastel', 'light_vivid', 'light_cobalt', 'dark_standard', 'dark_theater', 'dark_modern'],
            $organization
        );
        $this->assertSame(array_merge($organization, ['light_poster', 'dark_drama', 'monochrome']), $production);
        $this->assertSame('ライト・ポスター', ThemePresets::presets(PublicPageSite::production())['light_poster']['label']);
        $this->assertSame('#f7f5f0', ThemePresets::customDefaults()['bg']);
    }

    public function test_default_is_light_standard_preset(): void
    {
        $theme = ThemeSettings::default();

        $this->assertSame(['mode' => 'preset', 'preset' => 'light_standard', 'custom' => ThemePresets::customDefaults()], $theme->toArray());
        $this->assertSame('#f7f5f0', $theme->resolve(PublicPageSite::organization())['bg']);
    }

    public function test_preset_mode_resolves_to_the_preset_values(): void
    {
        $theme = ThemeSettings::fromArray(['mode' => 'preset', 'preset' => 'dark_theater', 'custom' => ['bg' => '#123456']], PublicPageSite::organization());
        $values = $theme->resolve(PublicPageSite::organization());

        $this->assertSame('#111114', $values['bg']);
        $this->assertSame('serif', $values['heading_font']);
        $this->assertSame('dark', $values['mode']);
        $this->assertArrayNotHasKey('label', $values);
        // Custom values are kept while a preset is used (StageArtCore stores them too).
        $this->assertSame('#123456', $theme->custom()['bg']);
    }

    public function test_custom_mode_overrides_the_preset_values(): void
    {
        $theme = ThemeSettings::fromArray([
            'mode' => 'custom',
            'preset' => 'light_pop',
            'custom' => ['bg' => '#ABCDEF', 'text' => '#000', 'heading_font' => 'serif', 'body_font' => 'sans'],
        ], PublicPageSite::organization());
        $values = $theme->resolve(PublicPageSite::organization());

        $this->assertSame('#abcdef', $values['bg']);
        $this->assertSame('#000', $values['text']);
        $this->assertSame('serif', $values['heading_font']);
        // Keys not sent fall back to StageArtCore's customDefaults(), as its sanitize() does.
        $this->assertSame('#ffffff', $values['surface']);
        // The light/dark mode still comes from the preset (as in ThemeRenderer::css()).
        $this->assertSame('light', $values['mode']);
    }

    public function test_production_only_presets_are_rejected_for_an_organization(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ThemeSettings::fromArray(['preset' => 'monochrome'], PublicPageSite::organization());
    }

    public function test_production_only_presets_are_accepted_for_a_production(): void
    {
        $theme = ThemeSettings::fromArray(['preset' => 'monochrome'], PublicPageSite::production());

        $this->assertSame('#141414', $theme->resolve(PublicPageSite::production())['bg']);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function invalidInputs(): array
    {
        return [
            'unknown preset' => [['preset' => 'neon']],
            'unknown mode' => [['mode' => 'auto']],
            'color without #' => [['custom' => ['bg' => 'ffffff']]],
            'css injection in a color' => [['custom' => ['bg' => '#fff;background:url(x)']]],
            'named color' => [['custom' => ['text' => 'red']]],
            'unknown font' => [['custom' => ['body_font' => 'Comic Sans']]],
            'custom not an object' => [['custom' => 'x']],
        ];
    }

    /**
     * @dataProvider invalidInputs
     *
     * @param array<string, mixed> $input
     */
    public function test_invalid_values_are_rejected(array $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        ThemeSettings::fromArray($input, PublicPageSite::production());
    }
}
