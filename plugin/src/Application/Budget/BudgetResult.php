<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Domain\Budget\Budget;

final class BudgetResult
{
    public string $id;
    public string $productionId;
    public string $name;
    public string $status;
    /** @var BudgetLineResult[] */
    public array $lines;
    public string $createdBy;
    public string $createdAt;
    public string $updatedBy;
    public string $updatedAt;
    public ?string $activatedBy;
    public ?string $activatedAt;

    /**
     * @param BudgetLineResult[] $lines
     */
    private function __construct(
        string $id,
        string $productionId,
        string $name,
        string $status,
        array $lines,
        string $createdBy,
        string $createdAt,
        string $updatedBy,
        string $updatedAt,
        ?string $activatedBy,
        ?string $activatedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->name = $name;
        $this->status = $status;
        $this->lines = $lines;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $updatedAt;
        $this->activatedBy = $activatedBy;
        $this->activatedAt = $activatedAt;
    }

    public static function fromDomain(Budget $budget): self
    {
        return new self(
            $budget->id()->toString(),
            $budget->productionId()->toString(),
            $budget->name(),
            $budget->status()->toString(),
            array_map(static fn ($line): BudgetLineResult => BudgetLineResult::fromDomain($line), $budget->lines()),
            $budget->createdBy()->toString(),
            $budget->createdAt()->format(DATE_ATOM),
            $budget->updatedBy()->toString(),
            $budget->updatedAt()->format(DATE_ATOM),
            $budget->activatedBy() !== null ? $budget->activatedBy()->toString() : null,
            $budget->activatedAt() !== null ? $budget->activatedAt()->format(DATE_ATOM) : null
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
            'name' => $this->name,
            'status' => $this->status,
            'lines' => array_map(static fn (BudgetLineResult $line): array => $line->toArray(), $this->lines),
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_by' => $this->updatedBy,
            'updated_at' => $this->updatedAt,
            'activated_by' => $this->activatedBy,
            'activated_at' => $this->activatedAt,
        ];
    }
}
