<?php

declare(strict_types=1);

namespace StageArt\Domain\Follow;

use DateTimeImmutable;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

/**
 * docs/04-DomainModel/Follow.md: a relationship of interest, deliberately
 * separate from Membership/Participant ("Followによって、所属、参加、管理
 * 権限は一切発生しない") - this Aggregate never grants Authorization, and
 * no Application-layer code should ever treat its existence as a
 * Membership check.
 *
 * One row per (Person, Organization) pair (see Installer.php's UNIQUE
 * KEY): re-following after an unfollow() reactivates the same row via
 * follow() rather than creating a second one, matching Follow.md's "同一
 * Personが同一Organizationを重複してFollowすることはできない".
 */
final class OrganizationFollow
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_UNFOLLOWED = 'UNFOLLOWED';

    private OrganizationFollowId $id;
    private PersonId $personId;
    private OrganizationId $organizationId;
    private string $status;
    private DateTimeImmutable $followedAt;
    private ?DateTimeImmutable $unfollowedAt;

    private function __construct(
        OrganizationFollowId $id,
        PersonId $personId,
        OrganizationId $organizationId,
        string $status,
        DateTimeImmutable $followedAt,
        ?DateTimeImmutable $unfollowedAt
    ) {
        $this->id = $id;
        $this->personId = $personId;
        $this->organizationId = $organizationId;
        $this->status = $status;
        $this->followedAt = $followedAt;
        $this->unfollowedAt = $unfollowedAt;
    }

    public static function create(PersonId $personId, OrganizationId $organizationId): self
    {
        return new self(
            OrganizationFollowId::generate(),
            $personId,
            $organizationId,
            self::STATUS_ACTIVE,
            new DateTimeImmutable(),
            null
        );
    }

    public static function reconstitute(
        OrganizationFollowId $id,
        PersonId $personId,
        OrganizationId $organizationId,
        string $status,
        DateTimeImmutable $followedAt,
        ?DateTimeImmutable $unfollowedAt
    ): self {
        return new self($id, $personId, $organizationId, $status, $followedAt, $unfollowedAt);
    }

    public function id(): OrganizationFollowId
    {
        return $this->id;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function followedAt(): DateTimeImmutable
    {
        return $this->followedAt;
    }

    public function unfollowedAt(): ?DateTimeImmutable
    {
        return $this->unfollowedAt;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Reactivates an existing (previously unfollowed) row - `followedAt`
     * refreshes to now, matching a genuine new Follow relationship. */
    public function follow(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->followedAt = new DateTimeImmutable();
        $this->unfollowedAt = null;
    }

    public function unfollow(): void
    {
        $this->status = self::STATUS_UNFOLLOWED;
        $this->unfollowedAt = new DateTimeImmutable();
    }
}
