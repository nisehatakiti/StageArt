<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;

/**
 * One append-only entry of an Expense's 申請・承認履歴
 * (ExpenseApprovalWorkflow.md "Approval Amount Adjustment": the
 * requested and approved amounts must both stay traceable, and the
 * 再申請 history with them). Lines are snapshotted because an Expense's
 * own Lines are replaced on every edit.
 *
 * `revision` is the Expense's submission revision the entry belongs to;
 * (expenseId, revision, action) is unique, so the same submission can
 * never be approved or rejected twice.
 */
final class ExpenseReview
{
    public const SUBMITTED = 'SUBMITTED';
    public const WITHDRAWN = 'WITHDRAWN';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';
    /** Schema 1.44.0: a pre-workflow CONFIRMED Expense carried over to POSTED (LegacyExpenseMigration). */
    public const MIGRATED = 'MIGRATED';

    /**
     * That carry-over undone by an operator (LegacyExpenseMigrationRestore,
     * ExpenseApprovalWorkflow.md Version 1.8): the Expense is CONFIRMED
     * again, the MIGRATED entry stays, and the Expense is never migrated
     * again. Not a business step - written only by the restore.
     */
    public const MIGRATION_REVERTED = 'MIGRATION_REVERTED';

    private const VALID_ACTIONS = [self::SUBMITTED, self::WITHDRAWN, self::APPROVED, self::REJECTED, self::MIGRATED, self::MIGRATION_REVERTED];

    private ExpenseId $expenseId;
    private int $revision;
    private string $action;
    private ?int $requestedAmount;
    private ?int $approvedAmount;
    /** @var array<int, array{account_id: string, amount: int, description: ?string}> */
    private array $linesSnapshot;
    private ?string $reason;
    private PersonId $actor;
    private DateTimeImmutable $actedAt;

    /**
     * @param array<int, array{account_id: string, amount: int, description: ?string}> $linesSnapshot
     */
    private function __construct(
        ExpenseId $expenseId,
        int $revision,
        string $action,
        ?int $requestedAmount,
        ?int $approvedAmount,
        array $linesSnapshot,
        ?string $reason,
        PersonId $actor,
        DateTimeImmutable $actedAt
    ) {
        if (! in_array($action, self::VALID_ACTIONS, true)) {
            throw new InvalidArgumentException("Invalid ExpenseReview action: {$action}");
        }

        $this->expenseId = $expenseId;
        $this->revision = $revision;
        $this->action = $action;
        $this->requestedAmount = $requestedAmount;
        $this->approvedAmount = $approvedAmount;
        $this->linesSnapshot = $linesSnapshot;
        $this->reason = $reason;
        $this->actor = $actor;
        $this->actedAt = $actedAt;
    }

    /**
     * @param ExpenseLine[] $lines
     */
    public static function record(
        ExpenseId $expenseId,
        int $revision,
        string $action,
        ?int $requestedAmount,
        ?int $approvedAmount,
        array $lines,
        ?string $reason,
        PersonId $actor
    ): self {
        $snapshot = array_map(
            static fn (ExpenseLine $line): array => [
                'account_id' => $line->accountId()->toString(),
                'amount' => $line->amount(),
                'description' => $line->description(),
            ],
            array_values($lines)
        );

        return new self($expenseId, $revision, $action, $requestedAmount, $approvedAmount, $snapshot, $reason, $actor, new DateTimeImmutable());
    }

    /**
     * @param array<int, array{account_id: string, amount: int, description: ?string}> $linesSnapshot
     */
    public static function reconstitute(
        ExpenseId $expenseId,
        int $revision,
        string $action,
        ?int $requestedAmount,
        ?int $approvedAmount,
        array $linesSnapshot,
        ?string $reason,
        PersonId $actor,
        DateTimeImmutable $actedAt
    ): self {
        return new self($expenseId, $revision, $action, $requestedAmount, $approvedAmount, $linesSnapshot, $reason, $actor, $actedAt);
    }

    public function expenseId(): ExpenseId
    {
        return $this->expenseId;
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function requestedAmount(): ?int
    {
        return $this->requestedAmount;
    }

    public function approvedAmount(): ?int
    {
        return $this->approvedAmount;
    }

    /**
     * @return array<int, array{account_id: string, amount: int, description: ?string}>
     */
    public function linesSnapshot(): array
    {
        return $this->linesSnapshot;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function actor(): PersonId
    {
        return $this->actor;
    }

    public function actedAt(): DateTimeImmutable
    {
        return $this->actedAt;
    }
}
