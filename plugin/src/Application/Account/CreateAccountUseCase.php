<?php

declare(strict_types=1);

namespace StageArt\Application\Account;

use InvalidArgumentException;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\OrganizationCapability;
use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Organization\OrganizationId;

/**
 * Account.md "Authorization": "Accountの作成・変更・無効化は、会計管理権限を持つ
 * Personが行う。Organization Administratorは、自身のOrganizationについて全権限を
 * 持つ。" Per Authorization.md's established RoleKey mapping (OWNER is the
 * concrete Role behind "Organization Administrator" throughout this
 * codebase), Create is OWNER-only.
 *
 * StageArt Core/Module Architecture Phase 3 (continued): migrated from
 * `OrganizationAuthorizationService`/`OrganizationRepositoryInterface`
 * to Core Contracts - the first real caller of both
 * `OrganizationContextContract` (previously unused by any Module) and
 * `AuthorizationContract::canForOrganization()`.
 */
final class CreateAccountUseCase
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

    public function execute(CreateAccountCommand $command): AccountResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new AccountAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->organizationContext->organizationExists($organizationId)) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        if (! $this->authorization->canForOrganization($requesterId, $organizationId, OrganizationCapability::OWNER)) {
            throw new AccountAccessDeniedException('Only the Organization Owner can create Accounts.');
        }

        $parentAccountId = null;
        if ($command->parentAccountId !== null && $command->parentAccountId !== '') {
            $parentAccountId = AccountId::fromString($command->parentAccountId);
            $parent = $this->accounts->findById($parentAccountId);

            if (! $parent || ! $parent->organizationId()->equals($organizationId)) {
                throw new InvalidArgumentException('parent_account_id must reference an Account in the same Organization.');
            }
        }

        $account = Account::create(
            $organizationId,
            $command->name,
            AccountType::fromString($command->type),
            $command->code,
            $parentAccountId
        );

        $this->accounts->save($account);

        return AccountResult::fromDomain($account);
    }
}
