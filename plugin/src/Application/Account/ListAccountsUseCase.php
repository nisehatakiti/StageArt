<?php

declare(strict_types=1);

namespace StageArt\Application\Account;

use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\OrganizationCapability;
use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;

/**
 * Read is intentionally broader than Create/Update (Account.md's
 * Authorization section only restricts write operations): any ACTIVE
 * Organization Member (OWNER or MEMBER) can list Accounts, since
 * Expense.md's "Expenseの作成(DRAFT)は、公演に関与する幅広いPersonが行える
 * ことを想定する" means regular Production Members need to see Account
 * choices to build an Expense Line - Account itself carries no sensitive
 * amount, only a classification name.
 *
 * StageArt Core/Module Architecture Phase 3 (continued): migrated from
 * `OrganizationAuthorizationService`/`OrganizationRepositoryInterface`
 * to Core Contracts, using `OrganizationCapability::MEMBER` (any ACTIVE
 * Membership) rather than `OWNER` - the broader Read check
 * `CreateAccountUseCase` doesn't need.
 */
final class ListAccountsUseCase
{
    private AccountRepositoryInterface $accounts;
    private OrganizationContextContract $organizationContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        AccountRepositoryInterface $accounts,
        OrganizationContextContract $organizationContext,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->accounts = $accounts;
        $this->organizationContext = $organizationContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    /**
     * @return AccountResult[]
     */
    public function execute(ListAccountsForOrganizationQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new AccountAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($query->organizationId);

        if (! $this->organizationContext->organizationExists($organizationId)) {
            throw new OrganizationNotFoundException($query->organizationId);
        }

        if (! $this->authorization->canForOrganization($requesterId, $organizationId, OrganizationCapability::MEMBER)) {
            throw new AccountAccessDeniedException('Only members of this Organization can view its Accounts.');
        }

        $accounts = $this->accounts->findByOrganizationId($organizationId);

        return array_map(static fn ($account): AccountResult => AccountResult::fromDomain($account), $accounts);
    }
}
