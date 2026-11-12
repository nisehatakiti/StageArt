<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use InvalidArgumentException;
use StageArt\Application\Accounting\PresetAccountCatalog;
use StageArt\Application\Accounting\PresetAccountProvisioner;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Budget\BudgetLine;
use StageArt\Domain\Organization\OrganizationId;

/**
 * Builds BudgetLine[] from 予算項目 input (`items`: key + amount + notes)
 * instead of raw Account ids, so the Budget screen never asks people to
 * pick an Account (BudgetManagementPolicy.md "Budget Input Screen").
 *
 * Items with amount 0 are left out (「不要な項目は0円のまま利用しない」);
 * every other amount must be a whole number of yen. Saving through items
 * registers the preset Accounts in the Organization (PresetAccountProvisioner),
 * so call this inside the Use Case's transaction, after authorization.
 */
final class BudgetItemLineFactory
{
    public const MAX_AMOUNT = 999999999999;

    private AccountRepositoryInterface $accounts;
    private PresetAccountProvisioner $presets;

    public function __construct(AccountRepositoryInterface $accounts, PresetAccountProvisioner $presets)
    {
        $this->accounts = $accounts;
        $this->presets = $presets;
    }

    /**
     * @param array<int, mixed> $rawItems
     * @return BudgetLine[]
     */
    public function build(array $rawItems, OrganizationId $organizationId): array
    {
        $parsed = $this->parse($rawItems);

        if (count($parsed) === 0) {
            throw new InvalidArgumentException('Enter an amount for at least one Budget item.');
        }

        $states = $this->presets->provision($organizationId);
        $lines = [];

        foreach ($parsed as $item) {
            $accountId = strpos($item['key'], BudgetItemResult::ACCOUNT_KEY_PREFIX) === 0
                ? $this->resolveOrganizationAccount(substr($item['key'], strlen(BudgetItemResult::ACCOUNT_KEY_PREFIX)), $organizationId)
                : $this->resolvePreset($item['key'], $states);

            $lines[] = BudgetLine::create($accountId, $item['amount'], $item['notes']);
        }

        return $lines;
    }

    /**
     * Validates every item before anything is registered.
     *
     * @param array<int, mixed> $rawItems
     * @return array<int, array{key: string, amount: int, notes: string|null}> items with an amount above 0
     */
    private function parse(array $rawItems): array
    {
        $seen = [];
        $parsed = [];

        foreach ($rawItems as $rawItem) {
            if (! is_array($rawItem) || ! isset($rawItem['key']) || ! is_string($rawItem['key']) || ! array_key_exists('amount', $rawItem)) {
                throw new InvalidArgumentException('Each Budget item requires key and amount.');
            }

            $key = $rawItem['key'];

            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Budget item {$key} is given more than once.");
            }

            $seen[$key] = true;

            if (strpos($key, BudgetItemResult::ACCOUNT_KEY_PREFIX) !== 0) {
                $preset = PresetAccountCatalog::findByKey($key);

                if ($preset === null || ! $preset->isBudgetItem()) {
                    throw new InvalidArgumentException("Unknown Budget item: {$key}");
                }
            }

            $amount = self::parseAmount($rawItem['amount']);
            $notes = isset($rawItem['notes']) && is_string($rawItem['notes']) && trim($rawItem['notes']) !== ''
                ? trim($rawItem['notes'])
                : null;

            if ($amount > 0) {
                $parsed[] = ['key' => $key, 'amount' => $amount, 'notes' => $notes];
            }
        }

        return $parsed;
    }

    /**
     * @param mixed $raw
     */
    private static function parseAmount($raw): int
    {
        if (is_int($raw)) {
            $amount = $raw;
        } elseif (is_string($raw) && preg_match('/^\d{1,12}$/', $raw) === 1) {
            $amount = (int) $raw;
        } else {
            throw new InvalidArgumentException('Budget item amount must be a whole number of yen, 0 or more.');
        }

        if ($amount < 0 || $amount > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('Budget item amount must be between 0 and ' . self::MAX_AMOUNT . ' yen.');
        }

        return $amount;
    }

    /**
     * @param array<string, \StageArt\Application\Accounting\PresetAccountState> $states
     */
    private function resolvePreset(string $key, array $states): AccountId
    {
        $state = $states[$key];

        if ($state->account === null) {
            throw new InvalidArgumentException("Budget item {$key} is unavailable: this Organization already has an Account of that name that cannot be used.");
        }

        return $state->account->id();
    }

    private function resolveOrganizationAccount(string $rawAccountId, OrganizationId $organizationId): AccountId
    {
        $account = null;

        try {
            $account = $this->accounts->findById(AccountId::fromString($rawAccountId));
        } catch (InvalidArgumentException $exception) {
            // Fall through to the same error as an unknown Account.
        }

        if (
            ! $account
            || ! $account->organizationId()->equals($organizationId)
            || ! in_array($account->type()->toString(), [AccountType::REVENUE, AccountType::EXPENSE], true)
        ) {
            throw new InvalidArgumentException('Budget item must reference a revenue or expense Account of the Production\'s own Organization.');
        }

        return $account->id();
    }
}
