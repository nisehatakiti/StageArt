<?php

declare(strict_types=1);

namespace StageArt\Application\Accounting;

use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Organization\OrganizationId;

/**
 * Registers PresetAccountCatalog in one Organization, additively: an
 * existing ACTIVE Account with the preset's name and type is reused, a
 * missing one is created (Account::create, name + type, no code - as
 * CheckIn\StandardAccountResolver does).
 *
 * Nothing existing is renamed, reactivated or changed. When the
 * Organization already has an Account of that name that cannot be reused
 * (INACTIVE, or another type), the preset is left unregistered and
 * reported as unavailable rather than adding a second Account of the same
 * name.
 */
final class PresetAccountProvisioner
{
    private AccountRepositoryInterface $accounts;

    public function __construct(AccountRepositoryInterface $accounts)
    {
        $this->accounts = $accounts;
    }

    /**
     * Read-only: how each preset currently stands in the Organization.
     *
     * @return array<string, PresetAccountState> keyed by preset key
     */
    public function inspect(OrganizationId $organizationId): array
    {
        $existing = $this->accounts->findByOrganizationId($organizationId);
        $states = [];

        foreach (PresetAccountCatalog::all() as $preset) {
            $states[$preset->key] = self::match($preset, $existing);
        }

        return $states;
    }

    /**
     * Creates every missing preset Account. Run inside the caller's
     * transaction.
     *
     * @return array<string, PresetAccountState> keyed by preset key
     */
    public function provision(OrganizationId $organizationId): array
    {
        $states = $this->inspect($organizationId);

        foreach (PresetAccountCatalog::all() as $preset) {
            if ($states[$preset->key]->account !== null || $states[$preset->key]->blocked) {
                continue;
            }

            $account = Account::create($organizationId, $preset->name, AccountType::fromString($preset->type));
            $this->accounts->save($account);
            $states[$preset->key] = new PresetAccountState($preset, $account, false);
        }

        return $states;
    }

    /**
     * @param Account[] $existing
     */
    private static function match(PresetAccount $preset, array $existing): PresetAccountState
    {
        $blocked = false;

        foreach ($existing as $account) {
            if ($account->name() !== $preset->name) {
                continue;
            }

            if ($account->type()->toString() === $preset->type && $account->isActive()) {
                return new PresetAccountState($preset, $account, false);
            }

            $blocked = true;
        }

        return new PresetAccountState($preset, null, $blocked);
    }
}
