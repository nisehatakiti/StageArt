<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use StageArt\Domain\ProductionDelegate\ProductionDelegate;

final class ProductionDelegateResult
{
    public string $id;
    public string $productionId;
    public string $personId;
    public string $role;
    public string $status;
    public string $createdBy;
    public string $createdAt;
    public string $updatedBy;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $productionId,
        string $personId,
        string $role,
        string $status,
        string $createdBy,
        string $createdAt,
        string $updatedBy,
        string $updatedAt
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

    public static function fromDomain(ProductionDelegate $delegate): self
    {
        return new self(
            $delegate->id()->toString(),
            $delegate->productionId()->toString(),
            $delegate->personId()->toString(),
            $delegate->role()->toString(),
            $delegate->status(),
            $delegate->createdBy()->toString(),
            $delegate->createdAt()->format(DATE_ATOM),
            $delegate->updatedBy()->toString(),
            $delegate->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'production_id' => $this->productionId,
            'person_id' => $this->personId,
            'role' => $this->role,
            'status' => $this->status,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_by' => $this->updatedBy,
            'updated_at' => $this->updatedAt,
        ];
    }
}
