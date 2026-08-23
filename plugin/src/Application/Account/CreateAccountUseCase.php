<?php

declare(strict_types=1);

namespace StageArt\Application\Account;

use InvalidArgumentException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

/**
 * Account.md "Authorization": "Accountの作成・変更・無効化は、会計管理権限を持つ
 * Personが行う。Organization Administratorは、自身のOrganizationについて全権限を
 * 持つ。" Per Authorization.md's established RoleKey mapping (OWNER is the
 * concrete Role behind "Organization Administrator" throughout this
 * codebase), Create is OWNER-only.
 */
final class CreateAccountUseCase
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

    public function execute(CreateAccountCommand $command): AccountResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new AccountAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->organizations->findById($organizationId)) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        if (! $this->authorization->hasRole($requester, $organizationId, [RoleKey::OWNER])) {
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
