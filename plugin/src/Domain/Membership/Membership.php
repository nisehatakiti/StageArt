<?php

declare(strict_types=1);

namespace StageArt\Domain\Membership;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;

/**
 * StageArt Web β版: REQUESTED/REJECTED are now implemented (Join Key /
 * search-based membership requests - see RequestOrganizationMembershipUseCase),
 * added on top of the original ACTIVE-only slice. INVITED/SUSPENDED/LEFT
 * remain future work (docs/04-DomainModel/Membership.md's full lifecycle) -
 * no organization-initiated invite flow or suspend/leave flow exists yet,
 * only Person-initiated requests + Organization Owner approval/rejection.
 */
final class Membership
{
    public const STATUS_REQUESTED = 'REQUESTED';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REJECTED = 'REJECTED';

    private MembershipId $id;
    private OrganizationId $organizationId;
    private PersonId $personId;
    private RoleKey $roleKey;
    private string $status;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $joinedAt;

    private function __construct(
        MembershipId $id,
        OrganizationId $organizationId,
        PersonId $personId,
        RoleKey $roleKey,
        string $status,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $joinedAt = null
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->personId = $personId;
        $this->roleKey = $roleKey;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->joinedAt = $joinedAt;
    }

    public static function create(OrganizationId $organizationId, PersonId $personId, RoleKey $roleKey): self
    {
        $now = new DateTimeImmutable();

        return new self(
            MembershipId::generate(),
            $organizationId,
            $personId,
            $roleKey,
            self::STATUS_ACTIVE,
            $now,
            $now
        );
    }

    /**
     * docs/04-DomainModel/Membership.md's "Membership Request": a Person
     * requesting to join an Organization (via Join Key or search) starts
     * REQUESTED, not ACTIVE - see approve()/reject(). Always MEMBER Role;
     * a requester can never grant themselves OWNER.
     */
    public static function requestMembership(OrganizationId $organizationId, PersonId $personId): self
    {
        return new self(
            MembershipId::generate(),
            $organizationId,
            $personId,
            RoleKey::member(),
            self::STATUS_REQUESTED,
            new DateTimeImmutable(),
            null
        );
    }

    /**
     * The only sanctioned way to create an OWNER Membership. Kept
     * distinct from create() so call sites make the Organization Owner
     * Invariant (exactly one OWNER Membership per Organization) visible
     * in code, rather than looking like an ordinary Membership creation
     * that happens to pass RoleKey::owner(). Enforcing the invariant
     * itself (checking no other OWNER already exists) is the caller's
     * (Application layer's) responsibility, since it spans multiple
     * Membership rows and this Aggregate only ever sees one.
     */
    public static function createOwnerMembership(OrganizationId $organizationId, PersonId $personId): self
    {
        return self::create($organizationId, $personId, RoleKey::owner());
    }

    public static function reconstitute(
        MembershipId $id,
        OrganizationId $organizationId,
        PersonId $personId,
        RoleKey $roleKey,
        string $status,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $joinedAt = null
    ): self {
        return new self($id, $organizationId, $personId, $roleKey, $status, $createdAt, $joinedAt);
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }

    public function joinedAt(): ?DateTimeImmutable
    {
        return $this->joinedAt;
    }

    /** Only a REQUESTED Membership can be approved - matching
     * Membership.md's "Approval" section ("REQUESTEDのMembershipをACTIVE
     * へ変更するには...承認が必要"), not an arbitrary status jump. */
    public function approve(): void
    {
        if ($this->status !== self::STATUS_REQUESTED) {
            throw new InvalidArgumentException('Only a REQUESTED Membership can be approved.');
        }

        $this->status = self::STATUS_ACTIVE;
        $this->joinedAt = new DateTimeImmutable();
    }

    public function reject(): void
    {
        if ($this->status !== self::STATUS_REQUESTED) {
            throw new InvalidArgumentException('Only a REQUESTED Membership can be rejected.');
        }

        $this->status = self::STATUS_REJECTED;
    }

    /**
     * Deliberately unguarded at this level: Membership cannot by itself
     * know whether changing its own Role would leave an Organization
     * with zero or multiple OWNERs, since that invariant spans other
     * Membership rows it has no visibility into. Only
     * OwnerTransferUseCase is expected to call this toward/away from
     * RoleKey::OWNER.
     */
    public function changeRole(RoleKey $roleKey): void
    {
        $this->roleKey = $roleKey;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
