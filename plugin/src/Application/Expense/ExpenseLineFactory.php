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
 */
final class ExpenseLineFactory
{
    private AccountRepositoryInterface $accounts;

    public function __construct(AccountRepositoryInterface $accounts)
    {
        $this->accounts = $accounts;
    }

    /**
     * @param array<int, array{account_id: string, amount: int, description?: string|null}> $rawLines
     * @return ExpenseLine[]
     */
    public function build(array $rawLines, OrganizationId $organizationId): array
    {
        if (count($rawLines) === 0) {
            throw new InvalidArgumentException('An Expense must have at least one Expense Line.');
        }

        return array_map(function (array $rawLine) use ($organizationId): ExpenseLine {
            if (! isset($rawLine['account_id'], $rawLine['amount'])) {
                throw new InvalidArgumentException('Each Expense Line requires account_id and amount.');
            }

            $accountId = AccountId::fromString((string) $rawLine['account_id']);
            $account = $this->accounts->findById($accountId);

            if (! $account || ! $account->organizationId()->equals($organizationId)) {
                throw new InvalidArgumentException('account_id must reference an Account in the Production\'s own Organization.');
            }

            return ExpenseLine::create($accountId, (int) $rawLine['amount'], $rawLine['description'] ?? null);
        }, $rawLines);
    }
}
