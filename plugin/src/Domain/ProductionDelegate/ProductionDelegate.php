<?php

declare(strict_types=1);

namespace StageArt\Domain\ProductionDelegate;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Role\RoleKey;

final class ProductionDelegate
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    private ProductionDelegateId $id;
    private ProductionId $productionId;
    private PersonId $personId;
    private RoleKey $role;
    private string $status;
    private PersonId $createdBy;
    private DateTimeImmutable $createdAt;
    private PersonId $updatedBy;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        ProductionDelegateId $id,
        ProductionId $productionId,
        PersonId $personId,
        RoleKey $role,
        string $status,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->personId = $personId;
        $this->role = $role;
        $this->status = $status;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Per ProductionDelegate.md "Create": initial Status is always
     * ACTIVE, and UpdatedBy/UpdatedAt start equal to CreatedBy/CreatedAt.
     */
    public static function create(
        ProductionId $productionId,
        PersonId $personId,
        RoleKey $role,
        PersonId $createdBy
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            ProductionDelegateId::generate(),
            $productionId,
            $personId,
            $role,
            self::STATUS_ACTIVE,
            $createdBy,
            $now,
            $createdBy,
            $now
        );
    }

    public static function reconstitute(
        ProductionDelegateId $id,
        ProductionId $productionId,
        PersonId $personId,
        RoleKey $role,
        string $status,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $productionId, $personId, $role, $status, $createdBy, $createdAt, $updatedBy, $updatedAt);
    }

    public function changeRole(RoleKey $role, PersonId $updatedBy): void
    {
        $this->role = $role;
        $this->touch($updatedBy);
    }

    public function activate(PersonId $updatedBy): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->touch($updatedBy);
    }

    public function deactivate(PersonId $updatedBy): void
    {
        $this->status = self::STATUS_INACTIVE;
        $this->touch($updatedBy);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    private function touch(PersonId $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ProductionDelegateId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function role(): RoleKey
    {
        return $this->role;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdBy(): PersonId
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedBy(): PersonId
    {
        return $this->updatedBy;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
