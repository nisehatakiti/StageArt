<?php

declare(strict_types=1);

namespace StageArt\Application\Accounting;

use StageArt\Domain\Account\AccountType;

/**
 * One entry of PresetAccountCatalog: an Account every Organization is
 * given as a preset (name + AccountType, matched by both - the same
 * convention CheckIn\StandardAccountResolver and
 * StandardPayableAccountResolver already use).
 *
 * A preset with a budgetLabel is also a 予算項目: the Budget input screen
 * shows that label and hint instead of the Account name, so people plan
 * 「かかる費用・見込める収入」 without thinking in Accounts, while each
 * Budget Line still references the Account (Budget.md "Budget Line").
 */
final class PresetAccount
{
    public string $key;
    public string $name;
    public string $type;
    public ?string $budgetLabel;
    public ?string $budgetHint;

    public function __construct(string $key, string $name, string $type, ?string $budgetLabel = null, ?string $budgetHint = null)
    {
        AccountType::fromString($type);

        $this->key = $key;
        $this->name = $name;
        $this->type = $type;
        $this->budgetLabel = $budgetLabel;
        $this->budgetHint = $budgetHint;
    }

    public function isBudgetItem(): bool
    {
        return $this->budgetLabel !== null;
    }
}
