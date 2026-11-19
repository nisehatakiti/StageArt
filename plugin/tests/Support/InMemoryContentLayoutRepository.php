<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\PublicPage\ContentLayout;
use StageArt\Domain\PublicPage\ContentLayoutRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

final class InMemoryContentLayoutRepository implements ContentLayoutRepositoryInterface
{
    /** @var array<string, ContentLayout> */
    private array $layouts = [];

    public int $saveCount = 0;

    public function find(PublicPageSite $site, string $ownerId): ?ContentLayout
    {
        return $this->layouts[$site->toString() . ':' . $ownerId] ?? null;
    }

    public function save(PublicPageSite $site, string $ownerId, ContentLayout $layout): void
    {
        $this->saveCount++;
        // Stored and read back as JSON, like the WordPress repository.
        $this->layouts[$site->toString() . ':' . $ownerId] = ContentLayout::fromStored(json_decode((string) json_encode($layout->blocks()), true));
    }
}
