<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

/**
 * Theme preset definitions for the Organization / Production public pages,
 * ported unchanged from StageArtCore `src/Domain/Site/Theme.php`
 * (presets() and customDefaults(): same keys, labels, colors and fonts).
 * The font choices are the ones StageArtCore's ThemeAdmin offers and its
 * ThemeRenderer understands (system / sans / serif).
 *
 * Content/layout data is kept separate from these presentation settings
 * (StageArtCore docs/theme-design.md).
 */
final class ThemePresets
{
    public const COLOR_KEYS = ['bg', 'surface', 'text', 'primary', 'accent', 'border'];
    public const FONT_KEYS = ['heading_font', 'body_font'];
    public const FONTS = ['system', 'sans', 'serif'];
    /** The labels StageArtCore's ThemeAdmin shows for FONTS. */
    public const FONT_LABELS = ['system' => 'システム標準', 'sans' => 'ゴシック系', 'serif' => '明朝・セリフ系'];
    public const DEFAULT_PRESET = 'light_standard';

    /** @return array<string, array<string, string>> */
    public static function presets(PublicPageSite $site): array
    {
        $base = [
            'light_standard' => ['label' => 'ライト・スタンダード', 'mode' => 'light', 'bg' => '#f7f5f0', 'surface' => '#ffffff', 'text' => '#242424', 'primary' => '#333333', 'accent' => '#9a6b2f', 'border' => '#ddd7cc', 'heading_font' => 'system', 'body_font' => 'system'],
            'light_elegant' => ['label' => 'ライト・エレガント', 'mode' => 'light', 'bg' => '#fbfaf7', 'surface' => '#ffffff', 'text' => '#302d2a', 'primary' => '#51453d', 'accent' => '#a87948', 'border' => '#e5ddd2', 'heading_font' => 'serif', 'body_font' => 'sans'],
            'light_pop' => ['label' => 'ライト・ポップ', 'mode' => 'light', 'bg' => '#fffdf8', 'surface' => '#ffffff', 'text' => '#272727', 'primary' => '#394b63', 'accent' => '#d36c4d', 'border' => '#e7e1d8', 'heading_font' => 'sans', 'body_font' => 'sans'],
            'light_pastel' => ['label' => 'ライト・パステル', 'mode' => 'light', 'bg' => '#f5fbf7', 'surface' => '#fffaf2', 'text' => '#30434a', 'primary' => '#4f7f78', 'accent' => '#e7a47a', 'border' => '#cfe2dc', 'heading_font' => 'sans', 'body_font' => 'sans'],
            'light_vivid' => ['label' => 'ライト・ビビッド', 'mode' => 'light', 'bg' => '#fff8f4', 'surface' => '#ffffff', 'text' => '#20202a', 'primary' => '#e83f6f', 'accent' => '#ffb000', 'border' => '#f2c6d3', 'heading_font' => 'sans', 'body_font' => 'sans'],
            'light_cobalt' => ['label' => 'ライト・コバルト', 'mode' => 'light', 'bg' => '#f4f7ff', 'surface' => '#ffffff', 'text' => '#1d2940', 'primary' => '#3157d5', 'accent' => '#e45b3f', 'border' => '#cbd5f5', 'heading_font' => 'sans', 'body_font' => 'sans'],
            'dark_standard' => ['label' => 'ダーク・スタンダード', 'mode' => 'dark', 'bg' => '#171717', 'surface' => '#222222', 'text' => '#eeeeee', 'primary' => '#f0f0f0', 'accent' => '#c59a61', 'border' => '#3b3b3b', 'heading_font' => 'system', 'body_font' => 'system'],
            'dark_theater' => ['label' => 'ダーク・シアター', 'mode' => 'dark', 'bg' => '#111114', 'surface' => '#1b1b20', 'text' => '#f0edf0', 'primary' => '#f4f0e8', 'accent' => '#b68b57', 'border' => '#36343a', 'heading_font' => 'serif', 'body_font' => 'sans'],
            'dark_modern' => ['label' => 'ダーク・モダン', 'mode' => 'dark', 'bg' => '#101820', 'surface' => '#18232d', 'text' => '#e9eef2', 'primary' => '#f5f7f9', 'accent' => '#6fa6b8', 'border' => '#33434f', 'heading_font' => 'sans', 'body_font' => 'sans'],
        ];

        if ($site->isProduction()) {
            $base['light_poster'] = ['label' => 'ライト・ポスター', 'mode' => 'light', 'bg' => '#faf8f3', 'surface' => '#ffffff', 'text' => '#24211e', 'primary' => '#2f2925', 'accent' => '#a54f3d', 'border' => '#ded5c8', 'heading_font' => 'serif', 'body_font' => 'sans'];
            $base['dark_drama'] = ['label' => 'ダーク・ドラマ', 'mode' => 'dark', 'bg' => '#0d0d0f', 'surface' => '#19191d', 'text' => '#eee9e1', 'primary' => '#f4eee5', 'accent' => '#a66d55', 'border' => '#353136', 'heading_font' => 'serif', 'body_font' => 'sans'];
            $base['monochrome'] = ['label' => 'モノクロ', 'mode' => 'dark', 'bg' => '#141414', 'surface' => '#202020', 'text' => '#f2f2f2', 'primary' => '#ffffff', 'accent' => '#bdbdbd', 'border' => '#3b3b3b', 'heading_font' => 'sans', 'body_font' => 'sans'];
        }

        return $base;
    }

    /** @return array<string, string> */
    public static function customDefaults(): array
    {
        return ['bg' => '#f7f5f0', 'surface' => '#ffffff', 'text' => '#242424', 'primary' => '#333333', 'accent' => '#9a6b2f', 'border' => '#ddd7cc', 'heading_font' => 'system', 'body_font' => 'system'];
    }
}
