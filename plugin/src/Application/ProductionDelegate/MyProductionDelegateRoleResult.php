<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use StageArt\Domain\ProductionDelegate\ProductionDelegate;

/**
 * PR #7 R14-B stage 9: one of the caller's own delegate rows
 * (ListMyProductionDelegateRolesUseCase) - the role, the row's status, and
 * whether it currently grants anything (§4.6), with the reason when not.
 */
final class MyProductionDelegateRoleResult
{
    public string $id;
    public string $productionId;
    public string $role;
    public string $status;
    public bool $effective;
    public ?string $ineffectiveReason;

    private function __construct(string $id, string $productionId, string $role, string $status, ?string $ineffectiveReason)
    {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->role = $role;
        $this->status = $status;
        $this->effective = $ineffectiveReason === null;
        $this->ineffectiveReason = $ineffectiveReason;
    }

    public static function fromDomain(ProductionDelegate $delegate, ?string $ineffectiveReason): self
    {
        return new self(
            $delegate->id()->toString(),
            $delegate->productionId()->toString(),
            $delegate->role()->toString(),
            (string) $delegate->status(),
            $delegate->isActive() ? $ineffectiveReason : 'INACTIVE'
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
            'role' => $this->role,
            'status' => $this->status,
            'effective' => $this->effective,
            'ineffective_reason' => $this->ineffectiveReason,
        ];
    }
}
