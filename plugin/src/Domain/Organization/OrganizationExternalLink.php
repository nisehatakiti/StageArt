<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

use DateTimeImmutable;

/**
 * docs/04-DomainModel/OrganizationPublicSiteStructure.md "OTHER LINKS"
 * (Confirmed): "その他リンクは劇団が自由に登録できる外部リンク一覧とする。...
 * V1ではサービスを限定しない。" A free-form label+url pair - no service
 * enum (unlike OrganizationSNS, which is a small fixed set of known
 * social platforms); any URL is allowed.
 */
final class OrganizationExternalLink
{
    private OrganizationExternalLinkId $id;
    private OrganizationId $organizationId;
    private string $label;
    private string $url;
    private int $displayOrder;
    /** StageArt Organization管理UI/UX統一: mirrors OrganizationSNS's own
     * visibility field exactly - 公開/非公開 toggle, defaults true. */
    private bool $visibility;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        OrganizationExternalLinkId $id,
        OrganizationId $organizationId,
        string $label,
        string $url,
        int $displayOrder,
        bool $visibility,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->label = $label;
        $this->url = $url;
        $this->displayOrder = $displayOrder;
        $this->visibility = $visibility;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(OrganizationId $organizationId, string $label, string $url, int $displayOrder, bool $visibility = true): self
    {
        $now = new DateTimeImmutable();

        return new self(OrganizationExternalLinkId::generate(), $organizationId, $label, $url, $displayOrder, $visibility, $now, $now);
    }

    public static function reconstitute(
        OrganizationExternalLinkId $id,
        OrganizationId $organizationId,
        string $label,
        string $url,
        int $displayOrder,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        bool $visibility = true
    ): self {
        return new self($id, $organizationId, $label, $url, $displayOrder, $visibility, $createdAt, $updatedAt);
    }

    public function update(string $label, string $url, int $displayOrder, bool $visibility = true): void
    {
        $this->label = $label;
        $this->url = $url;
        $this->displayOrder = $displayOrder;
        $this->visibility = $visibility;
        $this->touch();
    }

    public function isVisible(): bool
    {
        return $this->visibility;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): OrganizationExternalLinkId
    {
        return $this->id;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
