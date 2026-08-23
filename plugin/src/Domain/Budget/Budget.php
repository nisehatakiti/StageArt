<?php

declare(strict_types=1);

namespace StageArt\Domain\Budget;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * Budget.md: a named plan Scenario ("A会場案" etc.) for a Production,
 * composed of BudgetLines. Aggregate Root - BudgetLine is a child Entity
 * with no independent Repository (Budget.md "Concept": Budget -> Budget
 * Line -> Account).
 *
 * Version 1.0 (Budget.md "Active Budget"): only one ACTIVE Budget per
 * Production at a time. Enforcing "activating this one archives the
 * previous ACTIVE Budget" requires loading a second Budget aggregate
 * instance, which is a cross-aggregate concern the Application layer
 * coordinates (ActivateBudgetUseCase), not this class - mirroring how
 * Timetable Version publish/archive pairing is coordinated at the
 * Application layer elsewhere in this codebase.
 */
final class Budget
{
    private BudgetId $id;
    private ProductionId $productionId;
    private string $name;
    private BudgetStatus $status;
    /** @var BudgetLine[] */
    private array $lines;
    private PersonId $createdBy;
    private DateTimeImmutable $createdAt;
    private PersonId $updatedBy;
    private DateTimeImmutable $updatedAt;
    private ?PersonId $activatedBy;
    private ?DateTimeImmutable $activatedAt;

    /**
     * @param BudgetLine[] $lines
     */
    private function __construct(
        BudgetId $id,
        ProductionId $productionId,
        string $name,
        BudgetStatus $status,
        array $lines,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt,
        ?PersonId $activatedBy,
        ?DateTimeImmutable $activatedAt
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

    /**
     * @param BudgetLine[] $lines
     */
    public static function create(ProductionId $productionId, string $name, array $lines, PersonId $createdBy): self
    {
        self::assertValidName($name);

        $now = new DateTimeImmutable();

        return new self(
            BudgetId::generate(),
            $productionId,
            $name,
            BudgetStatus::draft(),
            $lines,
            $createdBy,
            $now,
            $createdBy,
            $now,
            null,
            null
        );
    }

    /**
     * @param BudgetLine[] $lines
     */
    public static function reconstitute(
        BudgetId $id,
        ProductionId $productionId,
        string $name,
        BudgetStatus $status,
        array $lines,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt,
        ?PersonId $activatedBy,
        ?DateTimeImmutable $activatedAt
    ): self {
        return new self(
            $id,
            $productionId,
            $name,
            $status,
            $lines,
            $createdBy,
            $createdAt,
            $updatedBy,
            $updatedAt,
            $activatedBy,
            $activatedAt
        );
    }

    private static function assertValidName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Budget name must not be empty.');
        }
    }

    /**
     * @param BudgetLine[] $lines
     */
    public function replaceLines(array $lines, PersonId $updatedBy): void
    {
        $this->assertDraft('Budget Lines');
        $this->lines = $lines;
        $this->touch($updatedBy);
    }

    public function rename(string $name, PersonId $updatedBy): void
    {
        $this->assertDraft('Budget name');
        self::assertValidName($name);
        $this->name = $name;
        $this->touch($updatedBy);
    }

    public function activate(PersonId $activatedBy): void
    {
        if (! $this->status->equals(BudgetStatus::draft())) {
            throw new InvalidArgumentException('Only a DRAFT Budget can be activated.');
        }

        $this->status = BudgetStatus::fromString(BudgetStatus::ACTIVE);
        $this->activatedBy = $activatedBy;
        $this->activatedAt = new DateTimeImmutable();
        $this->touch($activatedBy);
    }

    public function archive(PersonId $updatedBy): void
    {
        $this->status = BudgetStatus::fromString(BudgetStatus::ARCHIVED);
        $this->touch($updatedBy);
    }

    private function assertDraft(string $what): void
    {
        if (! $this->status->equals(BudgetStatus::draft())) {
            throw new InvalidArgumentException("{$what} can only be changed while the Budget is DRAFT.");
        }
    }

    private function touch(PersonId $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): BudgetId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): BudgetStatus
    {
        return $this->status;
    }

    /**
     * @return BudgetLine[]
     */
    public function lines(): array
    {
        return $this->lines;
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

    public function activatedBy(): ?PersonId
    {
        return $this->activatedBy;
    }

    public function activatedAt(): ?DateTimeImmutable
    {
        return $this->activatedAt;
    }
}
