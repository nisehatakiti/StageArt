<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use StageArt\Domain\PublicPage\PublicPageContent;
use StageArt\Domain\PublicPage\PublicPageContentRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;
use wpdb;

final class WordPressPublicPageContentRepository implements PublicPageContentRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_public_page_contents';
    }

    public function findById(string $id): ?PublicPageContent
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id), ARRAY_A);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByOwner(PublicPageSite $site, string $ownerId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE site = %s AND owner_id = %s ORDER BY created_at DESC, id ASC",
                $site->toString(),
                $ownerId
            ),
            ARRAY_A
        );

        return array_map(fn (array $row): PublicPageContent => $this->hydrate($row), $rows ?: []);
    }

    public function save(PublicPageContent $content): void
    {
        $result = $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->table} (id, site, owner_id, title, body, status, created_at, updated_at)
             VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body), status = VALUES(status), updated_at = VALUES(updated_at)",
            $content->id(),
            $content->site()->toString(),
            $content->ownerId(),
            $content->title(),
            $content->body(),
            $content->status(),
            $content->createdAt()->format('Y-m-d H:i:s'),
            $content->updatedAt()->format('Y-m-d H:i:s')
        ));

        if ($result === false) {
            throw new RuntimeException("Failed to save {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function delete(string $id): void
    {
        if ($this->wpdb->delete($this->table, ['id' => $id], ['%s']) === false) {
            throw new RuntimeException("Failed to delete from {$this->table}: " . $this->wpdb->last_error);
        }
    }

    /** @param array<string, string> $row */
    private function hydrate(array $row): PublicPageContent
    {
        $utc = new DateTimeZone('UTC');

        return PublicPageContent::reconstitute(
            (string) $row['id'],
            PublicPageSite::fromString((string) $row['site']),
            (string) $row['owner_id'],
            (string) $row['title'],
            (string) $row['body'],
            (string) $row['status'],
            new DateTimeImmutable((string) $row['created_at'], $utc),
            new DateTimeImmutable((string) $row['updated_at'], $utc)
        );
    }
}
