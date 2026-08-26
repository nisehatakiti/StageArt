<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Production\Production;

/**
 * The Production list shown on an Organization's Public Page
 * (docs/04-DomainModel/PublicPageUrlPolicy.md's "Next Production" /
 * "Latest Past Production" sections) - deliberately narrower than
 * PublicProductionResult, since a listing row needs less than a full
 * Production page. Never includes anything beyond what's already public
 * on the Production's own page.
 */
final class PublicOrganizationProductionSummary
{
    public string $id;
    public string $name;
    public string $slug;
    public string $status;
    public string $publishedAt;

    public function __construct(string $id, string $name, string $slug, string $status, string $publishedAt)
    {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->status = $status;
        $this->publishedAt = $publishedAt;
    }

    public static function fromDomain(Production $production): self
    {
        $slug = $production->slug();
        $publishedAt = $production->publishedAt();

        return new self(
            $production->id()->toString(),
            $production->name()->toString(),
            $slug !== null ? $slug->toString() : '',
            $production->status()->toString(),
            $publishedAt !== null ? $publishedAt->format(DATE_ATOM) : ''
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'published_at' => $this->publishedAt,
        ];
    }
}
