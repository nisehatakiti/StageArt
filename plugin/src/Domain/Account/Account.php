<?php

declare(strict_types=1);

namespace StageArt\Domain\Account;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Organization\OrganizationId;

/**
 * Account.md: the会計上の分類軸 (classification axis) both Budget Line and
 * Journal Entry Line reference. Deliberately minimal for this Phase:
 * Parent Account / Account Hierarchy / Posting Account distinctions are
 * defined in Blueprint but not enforced here (no business rule depends
 * on them yet - disclosed as an Open Item, not a silent omission).
 * AccountId is never reused across Organizations (§Organization Scope).
 */
final class Account
{
    private AccountId $id;
    private OrganizationId $organizationId;
    private string $name;
    private AccountType $type;
    private ?string $code;
    private ?AccountId $parentAccountId;
    private AccountStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        AccountId $id,
        OrganizationId $organizationId,
        string $name,
        AccountType $type,
        ?string $code,
        ?AccountId $parentAccountId,
        AccountStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->name = $name;
        $this->type = $type;
        $this->code = $code;
        $this->parentAccountId = $parentAccountId;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        OrganizationId $organizationId,
        string $name,
        AccountType $type,
        ?string $code = null,
        ?AccountId $parentAccountId = null
    ): self {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Account name must not be empty.');
        }

        $now = new DateTimeImmutable();

        return new self(
            AccountId::generate(),
            $organizationId,
            $name,
            $type,
            $code,
            $parentAccountId,
            AccountStatus::active(),
            $now,
            $now
        );
    }

    public static function reconstitute(
        AccountId $id,
        OrganizationId $organizationId,
        string $name,
        AccountType $type,
        ?string $code,
        ?AccountId $parentAccountId,
        AccountStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $organizationId, $name, $type, $code, $parentAccountId, $status, $createdAt, $updatedAt);
    }

    public function deactivate(): void
    {
        $this->status = AccountStatus::fromString(AccountStatus::INACTIVE);
        $this->touch();
    }

    public function activate(): void
    {
        $this->status = AccountStatus::active();
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): AccountType
    {
        return $this->type;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function parentAccountId(): ?AccountId
    {
        return $this->parentAccountId;
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status->equals(AccountStatus::active());
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
