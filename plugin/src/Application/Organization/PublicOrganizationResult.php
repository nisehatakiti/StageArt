<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use LogicException;
use StageArt\Domain\Organization\Organization;

/**
 * StageArt Web First Phase 2: deliberately narrower than
 * OrganizationResult - this is served to unauthenticated visitors of
 * `stageart.top/{organization-slug}`, so it must never carry `type`,
 * `status`, or any other internal/management-only field. Only what the
 * public page itself displays.
 */
final class PublicOrganizationResult
{
    public string $id;
    public string $name;
    public string $slug;
    public ?string $description;
    public ?string $logoUrl;
    public string $publishedAt;
    /** @var PublicOrganizationProductionSummary[] */
    public array $productions;
    /** StageArt 団体管理UI整理: only visibility=true SNS links reach the
     * public page, already ordered by display_order (see
     * GetPublicOrganizationBySlugUseCase's own filtering).
     * @var OrganizationSNSResult[] */
    public array $sns;
    /** No visibility flag - every registered external link is public
     * once the Organization itself is published (see
     * OrganizationExternalLink's own docblock).
     * @var OrganizationExternalLinkResult[] */
    public array $externalLinks;

    /**
     * @param PublicOrganizationProductionSummary[] $productions
     * @param OrganizationSNSResult[] $sns
     * @param OrganizationExternalLinkResult[] $externalLinks
     */
    private function __construct(
        string $id,
        string $name,
        string $slug,
        ?string $description,
        ?string $logoUrl,
        string $publishedAt,
        array $productions,
        array $sns,
        array $externalLinks
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->logoUrl = $logoUrl;
        $this->publishedAt = $publishedAt;
        $this->productions = $productions;
        $this->sns = $sns;
        $this->externalLinks = $externalLinks;
    }

    /**
     * @param PublicOrganizationProductionSummary[] $productions Published
     *        Productions belonging to this Organization - resolved by the
     *        caller (GetPublicOrganizationBySlugUseCase), not here, since
     *        that requires Project/Production repositories this narrow DTO
     *        has no business depending on.
     * @param OrganizationSNSResult[] $sns already filtered to
     *        visibility=true by the caller
     * @param OrganizationExternalLinkResult[] $externalLinks
     * @throws LogicException if the Organization has no slug or is not
     *                          published - callers must check
     *                          `isPublished()`/`slug() !== null` first
     *                          (see GetPublicOrganizationBySlugUseCase).
     */
    public static function fromDomain(
        Organization $organization,
        array $productions = [],
        array $sns = [],
        array $externalLinks = []
    ): self {
        $slug = $organization->slug();
        $publishedAt = $organization->publishedAt();

        if ($slug === null || $publishedAt === null) {
            throw new LogicException('Cannot build a PublicOrganizationResult for an unpublished Organization.');
        }

        return new self(
            $organization->id()->toString(),
            $organization->name()->toString(),
            $slug->toString(),
            $organization->description(),
            $organization->logoUrl(),
            $publishedAt->format(DATE_ATOM),
            $productions,
            $sns,
            $externalLinks
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
            'description' => $this->description,
            'logo_url' => $this->logoUrl,
            'published_at' => $this->publishedAt,
            'productions' => array_map(
                static fn (PublicOrganizationProductionSummary $p): array => $p->toArray(),
                $this->productions
            ),
            'sns' => array_map(
                static fn (OrganizationSNSResult $s): array => $s->toArray(),
                $this->sns
            ),
            'external_links' => array_map(
                static fn (OrganizationExternalLinkResult $l): array => $l->toArray(),
                $this->externalLinks
            ),
        ];
    }
}
