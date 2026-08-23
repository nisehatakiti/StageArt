<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Account;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Account\AccountAccessDeniedException;
use StageArt\Application\Account\CreateAccountCommand;
use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Account\ListAccountsForOrganizationQuery;
use StageArt\Application\Account\ListAccountsUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class AccountUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryAccountRepository $accounts;
    private CreateAccountUseCase $createAccount;
    private ListAccountsUseCase $listAccounts;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->accounts = new InMemoryAccountRepository();

        $authorization = new OrganizationAuthorizationService($this->people, $this->memberships);

        $this->createAccount = new CreateAccountUseCase($this->accounts, $this->organizations, $authorization);
        $this->listAccounts = new ListAccountsUseCase($this->accounts, $this->organizations, $authorization);
    }

    /**
     * @return array{0: Organization, 1: Person, 2: Person} Organization, Owner, non-owner Member
     */
    private function givenOrganizationWithOwnerAndMember(): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $owner = Person::create(1);
        $this->people->save($owner);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $owner->id()));

        $member = Person::create(2);
        $this->people->save($member);
        $this->memberships->save(Membership::create($organization->id(), $member->id(), RoleKey::member()));

        return [$organization, $owner, $member];
    }

    public function test_owner_can_create_account(): void
    {
        [$organization] = $this->givenOrganizationWithOwnerAndMember();

        $result = $this->createAccount->execute(
            new CreateAccountCommand(1, $organization->id()->toString(), 'チケット売上', AccountType::REVENUE)
        );

        $this->assertSame('チケット売上', $result->name);
        $this->assertSame('ACTIVE', $result->status);
    }

    public function test_non_owner_member_cannot_create_account(): void
    {
        [$organization] = $this->givenOrganizationWithOwnerAndMember();

        $this->expectException(AccountAccessDeniedException::class);

        $this->createAccount->execute(
            new CreateAccountCommand(2, $organization->id()->toString(), 'チケット売上', AccountType::REVENUE)
        );
    }

    public function test_non_owner_member_can_list_accounts(): void
    {
        [$organization] = $this->givenOrganizationWithOwnerAndMember();
        $this->createAccount->execute(
            new CreateAccountCommand(1, $organization->id()->toString(), 'チケット売上', AccountType::REVENUE)
        );

        $result = $this->listAccounts->execute(new ListAccountsForOrganizationQuery($organization->id()->toString(), 2));

        $this->assertCount(1, $result);
        $this->assertSame('チケット売上', $result[0]->name);
    }

    public function test_outsider_with_no_membership_cannot_list_accounts(): void
    {
        [$organization] = $this->givenOrganizationWithOwnerAndMember();

        $outsider = Person::create(99);
        $this->people->save($outsider);
        // Deliberately no Membership for $outsider in this Organization.

        $this->expectException(AccountAccessDeniedException::class);

        $this->listAccounts->execute(new ListAccountsForOrganizationQuery($organization->id()->toString(), 99));
    }
}
