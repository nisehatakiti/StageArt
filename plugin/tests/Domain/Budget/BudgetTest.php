<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Budget;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Budget\Budget;
use StageArt\Domain\Budget\BudgetLine;
use StageArt\Domain\Budget\BudgetStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

final class BudgetTest extends TestCase
{
    public function test_create_starts_in_draft(): void
    {
        $creator = PersonId::generate();
        $line = BudgetLine::create(AccountId::generate(), 500000);

        $budget = Budget::create(ProductionId::generate(), 'A会場案', [$line], $creator);

        $this->assertSame(BudgetStatus::DRAFT, $budget->status()->toString());
        $this->assertSame('A会場案', $budget->name());
        $this->assertCount(1, $budget->lines());
        $this->assertTrue($budget->createdBy()->equals($creator));
    }

    public function test_activate_transitions_to_active_and_records_activator(): void
    {
        $creator = PersonId::generate();
        $activator = PersonId::generate();
        $budget = Budget::create(ProductionId::generate(), 'A会場案', [BudgetLine::create(AccountId::generate(), 100)], $creator);

        $budget->activate($activator);

        $this->assertSame(BudgetStatus::ACTIVE, $budget->status()->toString());
        $this->assertTrue($budget->activatedBy()->equals($activator));
        $this->assertNotNull($budget->activatedAt());
    }

    public function test_activate_twice_is_rejected(): void
    {
        $budget = Budget::create(ProductionId::generate(), 'A案', [BudgetLine::create(AccountId::generate(), 100)], PersonId::generate());
        $budget->activate(PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        $budget->activate(PersonId::generate());
    }

    public function test_replace_lines_rejected_once_active(): void
    {
        $budget = Budget::create(ProductionId::generate(), 'A案', [BudgetLine::create(AccountId::generate(), 100)], PersonId::generate());
        $budget->activate(PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        $budget->replaceLines([BudgetLine::create(AccountId::generate(), 200)], PersonId::generate());
    }

    public function test_budget_line_amount_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BudgetLine::create(AccountId::generate(), 0);
    }

    public function test_archive_transitions_status(): void
    {
        $budget = Budget::create(ProductionId::generate(), 'A案', [BudgetLine::create(AccountId::generate(), 100)], PersonId::generate());
        $budget->activate(PersonId::generate());

        $budget->archive(PersonId::generate());

        $this->assertSame(BudgetStatus::ARCHIVED, $budget->status()->toString());
    }
}
