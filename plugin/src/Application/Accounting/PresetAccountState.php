<?php

declare(strict_types=1);

namespace StageArt\Application\Accounting;

use StageArt\Domain\Account\Account;

/**
 * A preset in one Organization: the Account it resolves to (null when not
 * registered yet), or blocked when an Account of the same name exists but
 * cannot be reused (see PresetAccountProvisioner).
 */
final class PresetAccountState
{
    public PresetAccount $preset;
    public ?Account $account;
    public bool $blocked;

    public function __construct(PresetAccount $preset, ?Account $account, bool $blocked)
    {
        $this->preset = $preset;
        $this->account = $account;
        $this->blocked = $blocked;
    }
}
