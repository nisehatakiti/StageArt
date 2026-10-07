<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationExternalLink;

final class OrganizationExternalLinkResult
{
    public string $id;
    public string $organizationId;
    public string $label;
    public string $url;
    public int $displayOrder;
    public bool $visibility;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $organizationId,
        string $label,
        string $url,
        int $displayOrder,
        bool $visibility,
        string $createdAt,
        string $updatedAt
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

    public static function fromDomain(OrganizationExternalLink $link): self
    {
        return new self(
            $link->id()->toString(),
            $link->organizationId()->toString(),
            $link->label(),
            $link->url(),
            $link->displayOrder(),
            $link->isVisible(),
            $link->createdAt()->format(DATE_ATOM),
            $link->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'label' => $this->label,
            'url' => $this->url,
            'display_order' => $this->displayOrder,
            'visibility' => $this->visibility,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
