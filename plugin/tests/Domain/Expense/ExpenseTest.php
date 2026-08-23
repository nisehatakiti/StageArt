<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Expense;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseLine;
use StageArt\Domain\Expense\ExpenseStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

final class ExpenseTest extends TestCase
{
    public function test_create_starts_in_draft(): void
    {
        $creator = PersonId::generate();
        $line = ExpenseLine::create(AccountId::generate(), 1500, '消耗品');

        $expense = Expense::create(ProductionId::generate(), [$line], $creator);

        $this->assertSame(ExpenseStatus::DRAFT, $expense->status()->toString());
        $this->assertSame(1500, $expense->totalAmount());
        $this->assertNull($expense->payerPersonId());
    }

    public function test_total_amount_sums_all_lines(): void
    {
        $expense = Expense::create(ProductionId::generate(), [
            ExpenseLine::create(AccountId::generate(), 500),
            ExpenseLine::create(AccountId::generate(), 1200),
            ExpenseLine::create(AccountId::generate(), 800),
        ], PersonId::generate());

        $this->assertSame(2500, $expense->totalAmount());
    }

    public function test_confirm_requires_at_least_one_line(): void
    {
        $expense = Expense::create(ProductionId::generate(), [], PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        $expense->confirm(PersonId::generate());
    }

    public function test_confirm_transitions_to_confirmed_and_records_confirmer(): void
    {
        $expense = Expense::create(
            ProductionId::generate(),
            [ExpenseLine::create(AccountId::generate(), 100)],
            PersonId::generate()
        );
        $confirmer = PersonId::generate();

        $expense->confirm($confirmer);

        $this->assertTrue($expense->isConfirmed());
        $this->assertTrue($expense->confirmedBy()->equals($confirmer));
        $this->assertNotNull($expense->confirmedAt());
    }

    public function test_confirm_twice_is_rejected(): void
    {
        $expense = Expense::create(
            ProductionId::generate(),
            [ExpenseLine::create(AccountId::generate(), 100)],
            PersonId::generate()
        );
        $expense->confirm(PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        $expense->confirm(PersonId::generate());
    }

    public function test_replace_lines_rejected_once_confirmed(): void
    {
        $expense = Expense::create(
            ProductionId::generate(),
            [ExpenseLine::create(AccountId::generate(), 100)],
            PersonId::generate()
        );
        $expense->confirm(PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        $expense->replaceLines([ExpenseLine::create(AccountId::generate(), 200)], PersonId::generate());
    }

    public function test_payer_person_id_is_preserved_when_set(): void
    {
        $payer = PersonId::generate();

        $expense = Expense::create(
            ProductionId::generate(),
            [ExpenseLine::create(AccountId::generate(), 100)],
            PersonId::generate(),
            $payer
        );

        $this->assertTrue($expense->payerPersonId()->equals($payer));
    }
}
