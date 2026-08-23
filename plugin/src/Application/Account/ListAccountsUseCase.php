<?php

declare(strict_types=1);

namespace StageArt\Application\Account;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

/**
 * Read is intentionally broader than Create/Update (Account.md's
 * Authorization section only restricts write operations): any ACTIVE
 * Organization Member (OWNER or MEMBER) can list Accounts, since
 * Expense.md's "Expenseの作成(DRAFT)は、公演に関与する幅広いPersonが行える
 * ことを想定する" means regular Production Members need to see Account
 * choices to build an Expense Line - Account itself carries no sensitive
 * amount, only a classification name.
 */
final class ListAccountsUseCase
{
    private AccountRepositoryInterface $accounts;
    private OrganizationRepositoryInterface $organizations;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        AccountRepositoryInterface $accounts,
        OrganizationRepositoryInterface $organizations,
        OrganizationAuthorizationService $authorization
    ) {
        $this->accounts = $accounts;
        $this->organizations = $organizations;
        $this->authorization = $authorization;
    }

    /**
     * @return AccountResult[]
     */
    public function execute(ListAccountsForOrganizationQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new AccountAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($query->organizationId);

        if (! $this->organizations->findById($organizationId)) {
            throw new OrganizationNotFoundException($query->organizationId);
        }

        if (! $this->authorization->hasRole($requester, $organizationId, [RoleKey::OWNER, RoleKey::MEMBER])) {
            throw new AccountAccessDeniedException('Only members of this Organization can view its Accounts.');
        }

        $accounts = $this->accounts->findByOrganizationId($organizationId);

        return array_map(static fn ($account): AccountResult => AccountResult::fromDomain($account), $accounts);
    }
}
