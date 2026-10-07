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
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        OrganizationExternalLinkId $id,
        OrganizationId $organizationId,
        string $label,
        string $url,
        int $displayOrder,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->label = $label;
        $this->url = $url;
        $this->displayOrder = $displayOrder;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(OrganizationId $organizationId, string $label, string $url, int $displayOrder): self
    {
        $now = new DateTimeImmutable();

        return new self(OrganizationExternalLinkId::generate(), $organizationId, $label, $url, $displayOrder, $now, $now);
    }

    public static function reconstitute(
        OrganizationExternalLinkId $id,
        OrganizationId $organizationId,
        string $label,
        string $url,
        int $displayOrder,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $organizationId, $label, $url, $displayOrder, $createdAt, $updatedAt);
    }

    public function update(string $label, string $url, int $displayOrder): void
    {
        $this->label = $label;
        $this->url = $url;
        $this->displayOrder = $displayOrder;
        $this->touch();
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
