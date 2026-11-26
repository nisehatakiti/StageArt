<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use InvalidArgumentException;

/**
 * ExpenseApprovalWorkflow.md "Expense Approval Lifecycle" plus the
 * 修正8 confirmed decisions: DRAFT (an unsubmitted 下書き) -> SUBMITTED ->
 * POSTED (approved and posted in the same step) or REJECTED (and back to
 * SUBMITTED on 再申請). CONFIRMED is the pre-workflow status the old
 * Confirm step produced; it is kept only so existing rows still load,
 * and Expense refuses every transition out of it (read-only legacy).
 */
final class ExpenseStatus
{
    public const DRAFT = 'DRAFT';
    public const SUBMITTED = 'SUBMITTED';
    public const POSTED = 'POSTED';
    public const REJECTED = 'REJECTED';
    public const CONFIRMED = 'CONFIRMED';

    private const VALID = [self::DRAFT, self::SUBMITTED, self::POSTED, self::REJECTED, self::CONFIRMED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ExpenseStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function is(string $value): bool
    {
        return $this->value === $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
