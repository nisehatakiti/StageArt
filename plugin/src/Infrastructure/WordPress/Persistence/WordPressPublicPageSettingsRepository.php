<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use InvalidArgumentException;
use RuntimeException;
use StageArt\Domain\PublicPage\HeroSettings;
use StageArt\Domain\PublicPage\PublicPageSettings;
use StageArt\Domain\PublicPage\PublicPageSettingsRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;
use StageArt\Domain\PublicPage\ThemeSettings;
use wpdb;

final class WordPressPublicPageSettingsRepository implements PublicPageSettingsRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_public_page_settings';
    }

    public function find(PublicPageSite $site, string $ownerId): ?PublicPageSettings
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE site = %s AND owner_id = %s", $site->toString(), $ownerId),
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        $theme = json_decode((string) $row['theme'], true);
        $hero = json_decode((string) $row['hero'], true);

        try {
            $themeSettings = is_array($theme) ? ThemeSettings::fromArray($theme, $site) : ThemeSettings::default();
        } catch (InvalidArgumentException $exception) {
            // A stored value that is no longer valid (e.g. a preset removed
            // since) falls back to the default theme instead of breaking
            // the public page.
            $themeSettings = ThemeSettings::default();
        }

        return new PublicPageSettings(
            $site,
            $ownerId,
            $themeSettings,
            is_array($hero) ? HeroSettings::fromStored($hero) : HeroSettings::disabled()
        );
    }

    public function save(PublicPageSettings $settings): void
    {
        $now = current_time('mysql', true);
        $theme = wp_json_encode($settings->theme()->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hero = wp_json_encode($settings->hero()->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // One statement, so two saves of the same page at once cannot both
        // try to INSERT (the primary key is site + owner_id).
        $result = $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->table} (site, owner_id, theme, hero, created_at, updated_at)
             VALUES (%s, %s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE theme = VALUES(theme), hero = VALUES(hero), updated_at = VALUES(updated_at)",
            $settings->site()->toString(),
            $settings->ownerId(),
            $theme,
            $hero,
            $now,
            $now
        ));

        if ($result === false) {
            throw new RuntimeException("Failed to save {$this->table}: " . $this->wpdb->last_error);
        }
    }
}
