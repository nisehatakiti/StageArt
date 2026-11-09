<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Expense;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Expense\ExpenseWorkflowNotifier;
use StageArt\Core\Contract\ProductionCapabilityDirectoryContract;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseLine;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Tests\Support\FakeProductionCapabilityDirectoryContract;
use StageArt\Tests\Support\FakeProductionContextContract;
use StageArt\Tests\Support\RecordingNotificationContract;

/**
 * PR #6 review N2/F5: the notifier runs after the commit, so nothing that
 * fails in it - looking up the approvers, storing a notification - may
 * escape and turn the committed operation into an error. Failures are
 * logged with the notification type and the Expense id only.
 */
final class ExpenseWorkflowNotifierTest extends TestCase
{
    private ProductionId $productionId;
    private PersonId $applicant;
    private RecordingNotificationContract $notifications;
    private FakeProductionContextContract $productionContext;
    private string $log;

    protected function setUp(): void
    {
        $this->log = (string) tempnam(sys_get_temp_dir(), 'stageart-notifier-log');
        ini_set('error_log', $this->log);
        $this->productionId = ProductionId::generate();
        $this->applicant = PersonId::generate();
        $this->notifications = new RecordingNotificationContract();
        $this->productionContext = new FakeProductionContextContract();
        $this->productionContext->register($this->productionId, '架空公演');
    }

    protected function tearDown(): void
    {
        ini_restore('error_log');
        @unlink($this->log);
    }

    private function submittedExpense(): Expense
    {
        $expense = Expense::create($this->productionId, [ExpenseLine::create(AccountId::generate(), 5000)], $this->applicant);
        $expense->submit($this->applicant);

        return $expense;
    }

    private function logged(): string
    {
        return (string) file_get_contents($this->log);
    }

    public function test_an_approver_whose_notification_fails_does_not_keep_the_others_from_theirs(): void
    {
        [$first, $second, $third] = [PersonId::generate(), PersonId::generate(), PersonId::generate()];
        $directory = new FakeProductionCapabilityDirectoryContract();
        $directory->register($this->productionId, AccountingCapability::APPROVE_EXPENSE, $first, $second, $third);
        $this->notifications->failFor($second);
        $expense = $this->submittedExpense();

        (new ExpenseWorkflowNotifier($this->notifications, $directory, $this->productionContext))->submitted($expense);

        $this->assertSame([$first->toString(), $third->toString()], array_column($this->notifications->sent(), 'person_id'));
        $this->assertStringContainsString('expense_submitted for expense ' . $expense->id()->toString() . ' not sent: RuntimeException', $this->logged());
    }

    public function test_approvers_that_cannot_be_looked_up_are_logged_not_thrown(): void
    {
        $directory = new class () implements ProductionCapabilityDirectoryContract {
            public function personIdsWithProductionCapability(ProductionId $productionId, string $capability): array
            {
                throw new RuntimeException('Simulated directory failure.');
            }
        };
        $expense = $this->submittedExpense();

        (new ExpenseWorkflowNotifier($this->notifications, $directory, $this->productionContext))->submitted($expense);

        $this->assertSame([], $this->notifications->sent());
        $this->assertStringContainsString('expense_submitted for expense ' . $expense->id()->toString() . ' not sent', $this->logged());
    }

    public function test_the_log_names_no_person_amount_or_message(): void
    {
        $this->notifications->failAll();
        $expense = $this->submittedExpense();

        (new ExpenseWorkflowNotifier($this->notifications, new FakeProductionCapabilityDirectoryContract(), $this->productionContext))->rejected($expense);

        $this->assertStringContainsString('expense_rejected for expense ' . $expense->id()->toString(), $this->logged());
        $this->assertStringNotContainsString($this->applicant->toString(), $this->logged());
        $this->assertStringNotContainsString('5,000', $this->logged());
        $this->assertStringNotContainsString('Simulated', $this->logged(), 'Only the exception class, not its message');
    }
}
