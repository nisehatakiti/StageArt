<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

interface PublicPageContentRepositoryInterface
{
    public function findById(string $id): ?PublicPageContent;

    /** @return list<PublicPageContent> this page's contents, newest first */
    public function findByOwner(PublicPageSite $site, string $ownerId): array;

    public function save(PublicPageContent $content): void;

    public function delete(string $id): void;
}
