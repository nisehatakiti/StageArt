<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Accounting\PresetAccountProvisioner;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Organization\OrganizationId;

/**
 * The 予算項目 list of one Organization: REVENUE presets, then the
 * Organization's other REVENUE Accounts, then the same for EXPENSE.
 * Read-only - presets are registered only when a Budget is saved
 * (BudgetItemLineFactory).
 */
final class BudgetItemCatalog
{
    private AccountRepositoryInterface $accounts;
    private PresetAccountProvisioner $presets;

    public function __construct(AccountRepositoryInterface $accounts, PresetAccountProvisioner $presets)
    {
        $this->accounts = $accounts;
        $this->presets = $presets;
    }

    /**
     * @return BudgetItemResult[]
     */
    public function list(OrganizationId $organizationId): array
    {
        $states = $this->presets->inspect($organizationId);
        $presetAccountIds = [];

        foreach ($states as $state) {
            if ($state->account !== null) {
                $presetAccountIds[$state->account->id()->toString()] = true;
            }
        }

        $items = [];

        foreach ([AccountType::REVENUE, AccountType::EXPENSE] as $type) {
            foreach ($states as $state) {
                if ($state->preset->isBudgetItem() && $state->preset->type === $type) {
                    $items[] = BudgetItemResult::fromPreset($state);
                }
            }

            $others = [];

            foreach ($this->accounts->findByOrganizationId($organizationId) as $account) {
                if ($account->type()->toString() === $type && ! isset($presetAccountIds[$account->id()->toString()])) {
                    $others[] = BudgetItemResult::fromAccount($account);
                }
            }

            usort($others, static fn (BudgetItemResult $a, BudgetItemResult $b): int => strcmp($a->label, $b->label));
            array_push($items, ...$others);
        }

        return $items;
    }
}
