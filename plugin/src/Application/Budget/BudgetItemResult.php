<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Accounting\PresetAccountState;
use StageArt\Domain\Account\Account;

/**
 * One 予算項目 of the Budget input screen. Either a preset
 * (PresetAccountCatalog, key = the preset key) or another REVENUE /
 * EXPENSE Account the Organization already has (key = "account:{id}",
 * e.g. a legacy 「車両交通費」), so existing Budget Lines on such Accounts
 * stay visible and editable.
 *
 * account_id is null for a preset not registered in the Organization yet
 * (it is registered on the first save). available is false when the item
 * cannot take a new amount (see PresetAccountProvisioner, or an INACTIVE
 * Account).
 */
final class BudgetItemResult
{
    public const ACCOUNT_KEY_PREFIX = 'account:';

    public string $key;
    public string $label;
    public ?string $hint;
    public string $type;
    public ?string $accountId;
    public bool $preset;
    public bool $available;

    private function __construct(string $key, string $label, ?string $hint, string $type, ?string $accountId, bool $preset, bool $available)
    {
        $this->key = $key;
        $this->label = $label;
        $this->hint = $hint;
        $this->type = $type;
        $this->accountId = $accountId;
        $this->preset = $preset;
        $this->available = $available;
    }

    public static function fromPreset(PresetAccountState $state): self
    {
        return new self(
            $state->preset->key,
            (string) $state->preset->budgetLabel,
            $state->preset->budgetHint,
            $state->preset->type,
            $state->account !== null ? $state->account->id()->toString() : null,
            true,
            ! $state->blocked
        );
    }

    public static function fromAccount(Account $account): self
    {
        return new self(
            self::ACCOUNT_KEY_PREFIX . $account->id()->toString(),
            $account->name(),
            null,
            $account->type()->toString(),
            $account->id()->toString(),
            false,
            $account->isActive()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'hint' => $this->hint,
            'type' => $this->type,
            'account_id' => $this->accountId,
            'preset' => $this->preset,
            'available' => $this->available,
        ];
    }
}
