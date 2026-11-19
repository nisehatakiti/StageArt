<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\PublicPage\PublicPageContent;
use StageArt\Domain\PublicPage\PublicPageContentRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

final class InMemoryPublicPageContentRepository implements PublicPageContentRepositoryInterface
{
    /** @var array<string, PublicPageContent> */
    private array $contents = [];

    public function findById(string $id): ?PublicPageContent
    {
        return $this->contents[$id] ?? null;
    }

    public function findByOwner(PublicPageSite $site, string $ownerId): array
    {
        $own = array_values(array_filter($this->contents, static fn (PublicPageContent $content): bool => $content->belongsTo($site, $ownerId)));
        usort($own, static fn (PublicPageContent $a, PublicPageContent $b): int => $b->createdAt() <=> $a->createdAt());

        return $own;
    }

    public function save(PublicPageContent $content): void
    {
        $this->contents[$content->id()] = $content;
    }

    public function delete(string $id): void
    {
        unset($this->contents[$id]);
    }
}
