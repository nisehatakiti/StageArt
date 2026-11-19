<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

use InvalidArgumentException;

/**
 * Hero (background image + title + description) of one public page,
 * following StageArtCore's HeroAdmin (organization) and
 * ProductionHeroAdmin (production): on/off, a preset from the hero
 * manifest or an own image, and an optional title/description (empty
 * means "use the organization's / production's own name and
 * description").
 *
 * The own image is stored as a URL (StageArt stores uploads as URLs,
 * like the organization logo - there is no WordPress attachment).
 *
 * What is kept depends on the page, as in StageArtCore:
 * - production (ProductionHeroAdmin::save): turning the hero off clears
 *   everything; choosing a preset forgets the own image and vice versa.
 * - organization (HeroAdmin::save): everything is kept, also while off.
 */
final class HeroSettings
{
    public const TYPE_PRESET = 'preset';
    public const TYPE_CUSTOM = 'custom';

    public const TITLE_MAX_LENGTH = 255;
    public const DESCRIPTION_MAX_LENGTH = 1000;

    private bool $enabled;
    private string $type;
    private ?string $preset;
    private ?string $customImageUrl;
    private string $title;
    private string $description;

    private function __construct(bool $enabled, string $type, ?string $preset, ?string $customImageUrl, string $title, string $description)
    {
        $this->enabled = $enabled;
        $this->type = $type;
        $this->preset = $preset;
        $this->customImageUrl = $customImageUrl;
        $this->title = $title;
        $this->description = $description;
    }

    public static function disabled(): self
    {
        return new self(false, self::TYPE_PRESET, null, null, '', '');
    }

    /**
     * @param callable(string): bool $presetExists whether a preset id is in
     *        this page's hero manifest
     *
     * @throws InvalidArgumentException
     */
    public static function create(
        PublicPageSite $site,
        bool $enabled,
        string $type,
        ?string $preset,
        ?string $customImageUrl,
        string $title,
        string $description,
        callable $presetExists
    ): self {
        if (! in_array($type, [self::TYPE_PRESET, self::TYPE_CUSTOM], true)) {
            throw new InvalidArgumentException('Hero画像の種類が不正です。');
        }

        $preset = $preset === '' ? null : $preset;

        if ($preset !== null && ! $presetExists($preset)) {
            throw new InvalidArgumentException('選択できないHero画像です。');
        }

        $title = trim(preg_replace('/[\r\n\t]+/', ' ', $title) ?? '');
        $description = trim($description);

        if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            throw new InvalidArgumentException('Heroタイトルは' . self::TITLE_MAX_LENGTH . '文字以内で入力してください。');
        }

        if (mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            throw new InvalidArgumentException('Hero説明文は' . self::DESCRIPTION_MAX_LENGTH . '文字以内で入力してください。');
        }

        if ($enabled && $type === self::TYPE_PRESET && $preset === null) {
            throw new InvalidArgumentException('Hero画像のプリセットを選択してください。');
        }

        if ($enabled && $type === self::TYPE_CUSTOM && ($customImageUrl === null || $customImageUrl === '')) {
            throw new InvalidArgumentException('独自のHero画像をアップロードしてください。');
        }

        if ($site->isProduction()) {
            if (! $enabled) {
                return self::disabled();
            }

            return $type === self::TYPE_PRESET
                ? new self(true, $type, $preset, null, $title, $description)
                : new self(true, $type, null, $customImageUrl, $title, $description);
        }

        return new self($enabled, $type, $preset, $customImageUrl === '' ? null : $customImageUrl, $title, $description);
    }

    /**
     * Rebuilds stored settings without re-validating them against the
     * current manifest (a preset that later disappears from the manifest
     * simply shows no image - see imageUrl resolution in the application
     * layer).
     *
     * @param array<string, mixed> $stored
     */
    public static function fromStored(array $stored): self
    {
        $type = ($stored['type'] ?? '') === self::TYPE_CUSTOM ? self::TYPE_CUSTOM : self::TYPE_PRESET;

        return new self(
            ! empty($stored['enabled']),
            $type,
            isset($stored['preset']) && is_string($stored['preset']) && $stored['preset'] !== '' ? $stored['preset'] : null,
            isset($stored['custom_image_url']) && is_string($stored['custom_image_url']) && $stored['custom_image_url'] !== '' ? $stored['custom_image_url'] : null,
            is_string($stored['title'] ?? null) ? $stored['title'] : '',
            is_string($stored['description'] ?? null) ? $stored['description'] : ''
        );
    }

    /** A copy with a newly uploaded own image (the other values unchanged). */
    public function withCustomImageUrl(string $url): self
    {
        return new self($this->enabled, $this->type, $this->preset, $url, $this->title, $this->description);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function preset(): ?string
    {
        return $this->preset;
    }

    public function customImageUrl(): ?string
    {
        return $this->customImageUrl;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return array{enabled: bool, type: string, preset: ?string, custom_image_url: ?string, title: string, description: string}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'type' => $this->type,
            'preset' => $this->preset,
            'custom_image_url' => $this->customImageUrl,
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
