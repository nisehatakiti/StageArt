<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use InvalidArgumentException;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Budget\BudgetLine;
use StageArt\Domain\Organization\OrganizationId;

/**
 * Shared by CreateBudgetUseCase and UpdateBudgetUseCase: builds
 * BudgetLine[] from raw REST input, validating each referenced Account
 * both exists and belongs to the same Organization as the Production
 * the Budget is for (Account.md "Organization Scope": an Account never
 * crosses Organization boundaries).
 */
final class BudgetLineFactory
{
    private AccountRepositoryInterface $accounts;

    public function __construct(AccountRepositoryInterface $accounts)
    {
        $this->accounts = $accounts;
    }

    /**
     * @param array<int, array{account_id: string, amount: int, notes?: string|null}> $rawLines
     * @return BudgetLine[]
     */
    public function build(array $rawLines, OrganizationId $organizationId): array
    {
        if (count($rawLines) === 0) {
            throw new InvalidArgumentException('A Budget must have at least one Budget Line.');
        }

        return array_map(function (array $rawLine) use ($organizationId): BudgetLine {
            if (! isset($rawLine['account_id'], $rawLine['amount'])) {
                throw new InvalidArgumentException('Each Budget Line requires account_id and amount.');
            }

            $accountId = AccountId::fromString((string) $rawLine['account_id']);
            $account = $this->accounts->findById($accountId);

            if (! $account || ! $account->organizationId()->equals($organizationId)) {
                throw new InvalidArgumentException('account_id must reference an Account in the Production\'s own Organization.');
            }

            return BudgetLine::create($accountId, (int) $rawLine['amount'], $rawLine['notes'] ?? null);
        }, $rawLines);
    }
}
