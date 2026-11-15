<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use StageArt\Domain\Person\Person;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;

/**
 * ProductionDelegate実用化 instruction §2: the Backend REST shape was
 * previously personId-only, which a real "誰にこの仕事を任せているか" UI
 * cannot render. `personFamilyName`/`personGivenName` are resolved here
 * the same way `ParticipantRequestResult::fromDomain()` already resolves
 * a Participant request's target Person - both nullable, matching
 * `Person::familyName()`/`givenName()`'s own nullability (a Person who
 * has not yet completed the set-name.tsx screen). This is the one
 * Backend change this instruction's own §2 anticipates ("不足している場合
 * のみ、不足箇所を報告する") - a minimal, additive enrichment of this
 * feature's own Result, not a new endpoint and not a Person search
 * capability (see this feature's final report for the fuller context:
 * no such search capability exists anywhere in StageArt today).
 */
final class ProductionDelegateResult
{
    public string $id;
    public string $productionId;
    public string $personId;
    public ?string $personFamilyName;
    public ?string $personGivenName;
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
        ?string $personFamilyName,
        ?string $personGivenName,
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
        $this->personFamilyName = $personFamilyName;
        $this->personGivenName = $personGivenName;
        $this->role = $role;
        $this->status = $status;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(ProductionDelegate $delegate, ?Person $person = null): self
    {
        return new self(
            $delegate->id()->toString(),
            $delegate->productionId()->toString(),
            $delegate->personId()->toString(),
            $person?->familyName(),
            $person?->givenName(),
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
            'person_family_name' => $this->personFamilyName,
            'person_given_name' => $this->personGivenName,
            'role' => $this->role,
            'status' => $this->status,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_by' => $this->updatedBy,
            'updated_at' => $this->updatedAt,
        ];
    }
}
