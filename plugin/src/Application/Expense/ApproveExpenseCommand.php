<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

final class ApproveExpenseCommand
{
    public string $expenseId;
    public int $requestedByWordPressUserId;
    /** The submission revision the approver reviewed (修正8 §6.5). */
    public int $expectedRevision;
    /** Approved total; null approves the requested amount. */
    public ?int $approvedAmount;

    public function __construct(string $expenseId, int $requestedByWordPressUserId, int $expectedRevision, ?int $approvedAmount = null)
    {
        $this->expenseId = $expenseId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->expectedRevision = $expectedRevision;
        $this->approvedAmount = $approvedAmount;
    }
}
