<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use RuntimeException;
use StageArt\Domain\PublicPage\ContentLayout;
use StageArt\Domain\PublicPage\ContentLayoutRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;
use wpdb;

/**
 * One row per public page (site + owner_id), holding the layout's blocks as
 * JSON - the same value Core keeps in its stageart_core_homepage_sections
 * option, but per page instead of per WordPress site.
 */
final class WordPressContentLayoutRepository implements ContentLayoutRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_public_page_layouts';
    }

    public function find(PublicPageSite $site, string $ownerId): ?ContentLayout
    {
        $blocks = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT blocks FROM {$this->table} WHERE site = %s AND owner_id = %s", $site->toString(), $ownerId)
        );

        if ($blocks === null) {
            return null;
        }

        $decoded = json_decode((string) $blocks, true);

        return ContentLayout::fromStored(is_array($decoded) ? $decoded : []);
    }

    public function save(PublicPageSite $site, string $ownerId, ContentLayout $layout): void
    {
        $now = current_time('mysql', true);
        $blocks = wp_json_encode($layout->blocks(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->table} (site, owner_id, blocks, created_at, updated_at)
             VALUES (%s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE blocks = VALUES(blocks), updated_at = VALUES(updated_at)",
            $site->toString(),
            $ownerId,
            $blocks,
            $now,
            $now
        ));

        if ($result === false) {
            throw new RuntimeException("Failed to save {$this->table}: " . $this->wpdb->last_error);
        }
    }
}
