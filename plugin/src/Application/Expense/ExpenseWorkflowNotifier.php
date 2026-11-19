<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Core\Contract\ProductionCapabilityDirectoryContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Person\PersonId;

/**
 * ExpenseApprovalWorkflow.md "Approval Notification": a SUBMITTED
 * Expense is announced to everyone who may approve it, through the same
 * NotificationContract the attendance notifications use; the
 * notification carries the Expense as its target so the app can open it
 * directly. The applicant hears back when it is approved or rejected.
 *
 * Callers invoke these only after their transaction has committed, so a
 * rolled-back change is never announced. Delivery failures are already
 * contained by the dispatcher and never undo the change.
 */
final class ExpenseWorkflowNotifier
{
    public const TYPE_SUBMITTED = 'expense_submitted';
    public const TYPE_APPROVED = 'expense_approved';
    public const TYPE_REJECTED = 'expense_rejected';
    public const TARGET_TYPE = 'expense';

    private NotificationContract $notifications;
    private ProductionCapabilityDirectoryContract $capabilityDirectory;
    private ProductionContextContract $productionContext;

    public function __construct(
        NotificationContract $notifications,
        ProductionCapabilityDirectoryContract $capabilityDirectory,
        ProductionContextContract $productionContext
    ) {
        $this->notifications = $notifications;
        $this->capabilityDirectory = $capabilityDirectory;
        $this->productionContext = $productionContext;
    }

    /**
     * To every approver except the people who may not approve this
     * Expense themselves (its applicant, creator, payer).
     */
    public function submitted(Expense $expense): void
    {
        $message = sprintf(
            '「%s」で経費申請（%s円）が提出されました。内容を確認し、承認または却下してください。',
            $this->productionName($expense),
            number_format((int) $expense->requestedAmount())
        );

        foreach ($this->capabilityDirectory->personIdsWithProductionCapability($expense->productionId(), AccountingCapability::MANAGE) as $approver) {
            if (! $expense->isOwnExpense($approver)) {
                $this->send($approver, self::TYPE_SUBMITTED, $expense, $message);
            }
        }
    }

    public function approved(Expense $expense): void
    {
        $message = sprintf(
            '「%s」の経費申請が承認されました（申請額 %s円 / 承認額 %s円）。',
            $this->productionName($expense),
            number_format((int) $expense->requestedAmount()),
            number_format((int) $expense->approvedAmount())
        );

        $this->send($this->applicant($expense), self::TYPE_APPROVED, $expense, $message);
    }

    public function rejected(Expense $expense): void
    {
        $message = sprintf(
            '「%s」の経費申請（%s円）が却下されました。理由：%s',
            $this->productionName($expense),
            number_format((int) $expense->requestedAmount()),
            (string) $expense->rejectionReason()
        );

        $this->send($this->applicant($expense), self::TYPE_REJECTED, $expense, $message);
    }

    private function applicant(Expense $expense): PersonId
    {
        return $expense->submittedBy() ?? $expense->createdBy();
    }

    private function send(PersonId $recipient, string $type, Expense $expense, string $message): void
    {
        $this->notifications->notify($recipient, $type, [
            'production_id' => $expense->productionId()->toString(),
            'target_type' => self::TARGET_TYPE,
            'target_id' => $expense->id()->toString(),
            'message' => $message,
        ]);
    }

    private function productionName(Expense $expense): string
    {
        $production = $this->productionContext->getProduction($expense->productionId());

        return $production !== null ? $production->name : '公演';
    }
}
