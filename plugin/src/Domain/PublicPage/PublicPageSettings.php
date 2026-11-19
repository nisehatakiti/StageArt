<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

/**
 * Display settings (theme + hero) of one Organization or one Production
 * public page. Kept apart from the Organization/Production rows so saving
 * these settings never touches the content fields, and the Organization
 * and its Productions each have their own independent settings
 * (StageArtCore docs/theme-design.md: 公演テーマは公演単位で保持し、
 * 団体テーマとは独立して変更可能).
 */
final class PublicPageSettings
{
    private PublicPageSite $site;
    private string $ownerId;
    private ThemeSettings $theme;
    private HeroSettings $hero;

    public function __construct(PublicPageSite $site, string $ownerId, ThemeSettings $theme, HeroSettings $hero)
    {
        $this->site = $site;
        $this->ownerId = $ownerId;
        $this->theme = $theme;
        $this->hero = $hero;
    }

    /** What a page that has never been configured uses. */
    public static function defaults(PublicPageSite $site, string $ownerId): self
    {
        return new self($site, $ownerId, ThemeSettings::default(), HeroSettings::disabled());
    }

    public function site(): PublicPageSite
    {
        return $this->site;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function theme(): ThemeSettings
    {
        return $this->theme;
    }

    public function hero(): HeroSettings
    {
        return $this->hero;
    }

    public function withTheme(ThemeSettings $theme): self
    {
        return new self($this->site, $this->ownerId, $theme, $this->hero);
    }

    public function withHero(HeroSettings $hero): self
    {
        return new self($this->site, $this->ownerId, $this->theme, $hero);
    }
}
