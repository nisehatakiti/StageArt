<?php

declare(strict_types=1);

namespace StageArt\Domain\UserAccount;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;

/**
 * UserAccount is the Authentication Identity (docs/04-DomainModel/UserAccount.md):
 * "誰であるか" only. It never carries Organization/Membership/Role data -
 * Authorization is always evaluated starting from Person, not from here.
 * Password/OAuth mechanics are not implemented on this Entity; only the
 * account-level relationship to Person and its own lifecycle Status live
 * here, per UserAccount.md's "Passwordや OAuth処理そのものは UserAccount
 * Entityに直接実装しない".
 */
final class UserAccount
{
    private UserAccountId $id;
    private PersonId $personId;
    private UserAccountStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        UserAccountId $id,
        PersonId $personId,
        UserAccountStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->personId = $personId;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(PersonId $personId): self
    {
        $now = new DateTimeImmutable();

        return new self(UserAccountId::generate(), $personId, UserAccountStatus::active(), $now, $now);
    }

    public static function reconstitute(
        UserAccountId $id,
        PersonId $personId,
        UserAccountStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $personId, $status, $createdAt, $updatedAt);
    }

    public function activate(): void
    {
        $this->status = UserAccountStatus::fromString(UserAccountStatus::ACTIVE);
        $this->touch();
    }

    public function suspend(): void
    {
        $this->status = UserAccountStatus::fromString(UserAccountStatus::SUSPENDED);
        $this->touch();
    }

    public function disable(): void
    {
        $this->status = UserAccountStatus::fromString(UserAccountStatus::DISABLED);
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->status->equals(UserAccountStatus::fromString(UserAccountStatus::ACTIVE));
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): UserAccountId
    {
        return $this->id;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function status(): UserAccountStatus
    {
        return $this->status;
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
