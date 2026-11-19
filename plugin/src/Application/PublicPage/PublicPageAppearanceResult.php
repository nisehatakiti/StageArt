<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

/**
 * The resolved look of a public page, embedded as `public_page` in the
 * public Organization / Production responses: the theme values (preset or
 * custom, resolved like StageArtCore's ThemeRenderer) and the hero, or
 * null when the hero is off or its image cannot be resolved (StageArtCore's
 * *HeroPublic::styles() likewise output nothing without an image URL).
 */
final class PublicPageAppearanceResult
{
    /** @var array<string, string> */
    private array $theme;
    /** @var array{image_url: string, title: string, description: string}|null */
    private ?array $hero;

    /**
     * @param array<string, string> $theme
     * @param array{image_url: string, title: string, description: string}|null $hero
     */
    public function __construct(array $theme, ?array $hero)
    {
        $this->theme = $theme;
        $this->hero = $hero;
    }

    /** @return array{theme: array<string, string>, hero: array{image_url: string, title: string, description: string}|null} */
    public function toArray(): array
    {
        return ['theme' => $this->theme, 'hero' => $this->hero];
    }
}
