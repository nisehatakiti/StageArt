<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

use DateTimeImmutable;

/**
 * docs/04-DomainModel/OrganizationSNS.md (Confirmed): "OrganizationSNSは、
 * Organizationの公式外部SNSアカウントを管理するDomainである。" A public-profile
 * reference only (service/account_identifier/profile_url/display_name/
 * display_order/visibility) - deliberately carries no credential/token
 * field (doc's own "Security" section forbids it; see
 * OrganizationSNSRepositoryInterface's own docblock).
 */
final class OrganizationSNS
{
    private OrganizationSNSId $id;
    private OrganizationId $organizationId;
    private OrganizationSNSService $service;
    private string $accountIdentifier;
    private string $profileUrl;
    private ?string $displayName;
    private int $displayOrder;
    private bool $visibility;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        OrganizationSNSId $id,
        OrganizationId $organizationId,
        OrganizationSNSService $service,
        string $accountIdentifier,
        string $profileUrl,
        ?string $displayName,
        int $displayOrder,
        bool $visibility,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
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

    public static function create(
        OrganizationId $organizationId,
        OrganizationSNSService $service,
        string $accountIdentifier,
        string $profileUrl,
        ?string $displayName,
        int $displayOrder,
        bool $visibility
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            OrganizationSNSId::generate(),
            $organizationId,
            $service,
            $accountIdentifier,
            $profileUrl,
            $displayName,
            $displayOrder,
            $visibility,
            $now,
            $now
        );
    }

    public static function reconstitute(
        OrganizationSNSId $id,
        OrganizationId $organizationId,
        OrganizationSNSService $service,
        string $accountIdentifier,
        string $profileUrl,
        ?string $displayName,
        int $displayOrder,
        bool $visibility,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $organizationId,
            $service,
            $accountIdentifier,
            $profileUrl,
            $displayName,
            $displayOrder,
            $visibility,
            $createdAt,
            $updatedAt
        );
    }

    public function update(
        OrganizationSNSService $service,
        string $accountIdentifier,
        string $profileUrl,
        ?string $displayName,
        int $displayOrder,
        bool $visibility
    ): void {
        $this->service = $service;
        $this->accountIdentifier = $accountIdentifier;
        $this->profileUrl = $profileUrl;
        $this->displayName = $displayName;
        $this->displayOrder = $displayOrder;
        $this->visibility = $visibility;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): OrganizationSNSId
    {
        return $this->id;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function service(): OrganizationSNSService
    {
        return $this->service;
    }

    public function accountIdentifier(): string
    {
        return $this->accountIdentifier;
    }

    public function profileUrl(): string
    {
        return $this->profileUrl;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function isVisible(): bool
    {
        return $this->visibility;
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
