<?php

declare(strict_types=1);

namespace StageArt\Domain\Membership;

use DateTimeImmutable;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

/**
 * Deliberately minimal: only the ACTIVE status from the full lifecycle in
 * docs/04-DomainModel/Membership.md (REQUESTED/INVITED/ACTIVE/SUSPENDED/
 * LEFT/REJECTED) is implemented. Membership is created only as a side
 * effect of Organization creation in this slice; a standalone invite/join
 * workflow is future work.
 */
final class Membership
{
    public const STATUS_ACTIVE = 'ACTIVE';

    private MembershipId $id;
    private OrganizationId $organizationId;
    private PersonId $personId;
    private RoleKey $roleKey;
    private string $status;
    private DateTimeImmutable $createdAt;

    private function __construct(
        MembershipId $id,
        OrganizationId $organizationId,
        PersonId $personId,
        RoleKey $roleKey,
        string $status,
        DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->personId = $personId;
        $this->roleKey = $roleKey;
        $this->status = $status;
        $this->createdAt = $createdAt;
    }

    public static function create(OrganizationId $organizationId, PersonId $personId, RoleKey $roleKey): self
    {
        return new self(
            MembershipId::generate(),
            $organizationId,
            $personId,
            $roleKey,
            self::STATUS_ACTIVE,
            new DateTimeImmutable()
        );
    }

    public static function reconstitute(
        MembershipId $id,
        OrganizationId $organizationId,
        PersonId $personId,
        RoleKey $roleKey,
        string $status,
        DateTimeImmutable $createdAt
    ): self {
        return new self($id, $organizationId, $personId, $roleKey, $status, $createdAt);
    }

    public function id(): MembershipId
    {
        return $this->id;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function roleKey(): RoleKey
    {
        return $this->roleKey;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
