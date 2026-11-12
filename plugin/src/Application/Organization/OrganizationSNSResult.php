<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationSNS;

final class OrganizationSNSResult
{
    public string $id;
    public string $organizationId;
    public string $service;
    public string $accountIdentifier;
    public ?string $profileUrl;
    public ?string $displayName;
    public int $displayOrder;
    public bool $visibility;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $organizationId,
        string $service,
        string $accountIdentifier,
        ?string $profileUrl,
        ?string $displayName,
        int $displayOrder,
        bool $visibility,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->service = $service;
        $this->accountIdentifier = $accountIdentifier;
        $this->profileUrl = $profileUrl;
        $this->displayName = $displayName;
        $this->displayOrder = $displayOrder;
        $this->visibility = $visibility;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(OrganizationSNS $sns): self
    {
        return new self(
            $sns->id()->toString(),
            $sns->organizationId()->toString(),
            $sns->service()->toString(),
            $sns->accountIdentifier(),
            $sns->profileUrl(),
            $sns->displayName(),
            $sns->displayOrder(),
            $sns->isVisible(),
            $sns->createdAt()->format(DATE_ATOM),
            $sns->updatedAt()->format(DATE_ATOM)
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
            'service' => $this->service,
            'account_identifier' => $this->accountIdentifier,
            'profile_url' => $this->profileUrl,
            'display_name' => $this->displayName,
            'display_order' => $this->displayOrder,
            'visibility' => $this->visibility,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
