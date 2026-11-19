<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

use InvalidArgumentException;

/**
 * Saved theme choice of one public page: the same shape StageArtCore
 * stores (`['mode' => 'preset'|'custom', 'preset' => key, 'custom' => [...]]`).
 * The custom values are kept even while a preset is in use, as in
 * StageArtCore, so switching back to the custom mode restores them.
 *
 * Unlike StageArtCore's sanitize() (which silently falls back to
 * defaults), an unknown preset, color or font is rejected so an invalid
 * value is never stored.
 */
final class ThemeSettings
{
    public const MODE_PRESET = 'preset';
    public const MODE_CUSTOM = 'custom';

    private string $mode;
    private string $preset;
    /** @var array<string, string> */
    private array $custom;

    /** @param array<string, string> $custom */
    private function __construct(string $mode, string $preset, array $custom)
    {
        $this->mode = $mode;
        $this->preset = $preset;
        $this->custom = $custom;
    }

    public static function default(): self
    {
        return new self(self::MODE_PRESET, ThemePresets::DEFAULT_PRESET, ThemePresets::customDefaults());
    }

    /**
     * @param array<string, mixed> $input
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $input, PublicPageSite $site): self
    {
        $mode = $input['mode'] ?? self::MODE_PRESET;

        if (! in_array($mode, [self::MODE_PRESET, self::MODE_CUSTOM], true)) {
            throw new InvalidArgumentException('テーマのモードが不正です。');
        }

        $preset = $input['preset'] ?? ThemePresets::DEFAULT_PRESET;

        if (! is_string($preset) || ! array_key_exists($preset, ThemePresets::presets($site))) {
            throw new InvalidArgumentException('選択できないテーマです。');
        }

        $customInput = $input['custom'] ?? [];

        if (! is_array($customInput)) {
            throw new InvalidArgumentException('カスタム配色の形式が不正です。');
        }

        $custom = ThemePresets::customDefaults();

        foreach (ThemePresets::COLOR_KEYS as $key) {
            if (! array_key_exists($key, $customInput)) {
                continue;
            }

            $color = self::normalizeHexColor($customInput[$key]);

            if ($color === null) {
                throw new InvalidArgumentException('色の値が不正です（#rrggbb 形式で指定してください）。');
            }

            $custom[$key] = $color;
        }

        foreach (ThemePresets::FONT_KEYS as $key) {
            if (! array_key_exists($key, $customInput)) {
                continue;
            }

            if (! in_array($customInput[$key], ThemePresets::FONTS, true)) {
                throw new InvalidArgumentException('選択できないフォントです。');
            }

            $custom[$key] = $customInput[$key];
        }

        return new self($mode, $preset, $custom);
    }

    /** Same accepted forms as WordPress's sanitize_hex_color(): #rgb or #rrggbb. */
    private static function normalizeHexColor($value): ?string
    {
        if (! is_string($value) || ! preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $value)) {
            return null;
        }

        return strtolower($value);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function preset(): string
    {
        return $this->preset;
    }

    /** @return array<string, string> */
    public function custom(): array
    {
        return $this->custom;
    }

    /**
     * The values the public page uses, resolved the way StageArtCore's
     * ThemeRenderer::css() does: the preset's values, overridden by the
     * custom values in the custom mode.
     *
     * @return array<string, string>
     */
    public function resolve(PublicPageSite $site): array
    {
        $presets = ThemePresets::presets($site);
        $values = $presets[$this->preset] ?? $presets[ThemePresets::DEFAULT_PRESET];

        if ($this->mode === self::MODE_CUSTOM) {
            $values = array_merge($values, $this->custom);
        }

        unset($values['label']);

        return $values;
    }

    /** @return array{mode: string, preset: string, custom: array<string, string>} */
    public function toArray(): array
    {
        return ['mode' => $this->mode, 'preset' => $this->preset, 'custom' => $this->custom];
    }
}
