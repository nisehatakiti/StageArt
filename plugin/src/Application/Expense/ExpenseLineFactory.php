<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use InvalidArgumentException;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Expense\ExpenseLine;
use StageArt\Domain\Organization\OrganizationId;

/**
 * Mirrors Application\Budget\BudgetLineFactory - same Organization-match
 * validation, applied to Expense Lines instead of Budget Lines.
 *
 * The raw lines come straight from a request (PR #6 review N6/F6), so
 * anything malformed is refused as invalid input (422) rather than failing
 * with a 500 or being coerced: each line must be an object, account_id a
 * string, description a string or null, and amount a whole number of yen -
 * a JSON integer or a string of digits, as the approval API accepts
 * (ExpenseApprovalWorkflow.md Version 1.9). 100.9, "100.9", 1e3 and the
 * like are refused instead of being truncated to 100; the Domain still
 * requires it to be positive. The Lines' total must fit in an integer too.
 */
final class ExpenseLineFactory
{
    private AccountRepositoryInterface $accounts;

    public function __construct(AccountRepositoryInterface $accounts)
    {
        $this->accounts = $accounts;
    }

    /**
     * @param array<int, mixed> $rawLines
     * @return ExpenseLine[]
     */
    public function build(array $rawLines, OrganizationId $organizationId): array
    {
        if (count($rawLines) === 0) {
            throw new InvalidArgumentException('An Expense must have at least one Expense Line.');
        }

        $lines = array_map(function ($rawLine) use ($organizationId): ExpenseLine {
            if (! is_array($rawLine)) {
                throw new InvalidArgumentException('Each Expense Line must be an object with account_id and amount.');
            }

            if (! isset($rawLine['account_id'], $rawLine['amount'])) {
                throw new InvalidArgumentException('Each Expense Line requires account_id and amount.');
            }

            if (! is_string($rawLine['account_id'])) {
                throw new InvalidArgumentException('account_id must be a string.');
            }

            $amount = self::wholeNumber($rawLine['amount']);

            if ($amount === null) {
                throw new InvalidArgumentException('amount must be a whole number of yen.');
            }

            $description = $rawLine['description'] ?? null;

            if ($description !== null && ! is_string($description)) {
                throw new InvalidArgumentException('description must be a string.');
            }

            $accountId = AccountId::fromString($rawLine['account_id']);
            $account = $this->accounts->findById($accountId);

            if (! $account || ! $account->organizationId()->equals($organizationId)) {
                throw new InvalidArgumentException('account_id must reference an Account in the Production\'s own Organization.');
            }

            return ExpenseLine::create($accountId, $amount, $description);
        }, array_values($rawLines));

        $total = 0;

        foreach ($lines as $line) {
            if ($line->amount() > PHP_INT_MAX - $total) {
                throw new InvalidArgumentException('The Expense Lines\' total is too large.');
            }

            $total += $line->amount();
        }

        return $lines;
    }

    /**
     * The integer a request value holds, or null: a PHP int (a JSON
     * integer), or a string of an optional minus sign and digits without a
     * leading zero, within the int range - the same rule as the approval
     * API's revision and approved_amount.
     *
     * @param mixed $value
     */
    private static function wholeNumber($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^-?(0|[1-9][0-9]*)$/D', $value) !== 1) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }
}
