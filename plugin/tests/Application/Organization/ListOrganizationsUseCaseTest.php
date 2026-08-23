<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\CreateOrganizationCommand;
use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Application\Organization\DeleteOrganizationCommand;
use StageArt\Application\Organization\DeleteOrganizationUseCase;
use StageArt\Application\Organization\ListOrganizationsForPersonQuery;
use StageArt\Application\Organization\ListOrganizationsUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class ListOrganizationsUseCaseTest extends TestCase
{
    private function givenActiveUserAccount(
        InMemoryPersonRepository $people,
        InMemoryUserAccountRepository $userAccounts,
        int $wordPressUserId
    ): void {
        $person = Person::create($wordPressUserId);
        $people->save($person);
        $userAccounts->save(UserAccount::create($person->id()));
    }

    public function test_list_only_returns_organizations_the_caller_belongs_to(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $userAccounts = new InMemoryUserAccountRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $this->givenActiveUserAccount($people, $userAccounts, 1);
        $this->givenActiveUserAccount($people, $userAccounts, 2);

        $createOrganization = new CreateOrganizationUseCase(
            $organizations,
            $people,
            $memberships,
            new InMemoryTransactionManager()
        );
        $listOrganizations = new ListOrganizationsUseCase($organizations, $memberships, $authorization);

        $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A'));
        $createOrganization->execute(new CreateOrganizationCommand(2, 'Organization B'));

        $resultsForUserOne = $listOrganizations->execute(new ListOrganizationsForPersonQuery(1));

        $this->assertCount(1, $resultsForUserOne);
        $this->assertSame('Organization A', $resultsForUserOne[0]->name);
    }

    public function test_list_is_empty_for_a_wordpress_user_with_no_person(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $listOrganizations = new ListOrganizationsUseCase($organizations, $memberships, $authorization);

        $this->assertSame([], $listOrganizations->execute(new ListOrganizationsForPersonQuery(999)));
    }

    public function test_deleted_organization_disappears_from_the_list(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $userAccounts = new InMemoryUserAccountRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $this->givenActiveUserAccount($people, $userAccounts, 1);

        $createOrganization = new CreateOrganizationUseCase(
            $organizations,
            $people,
            $memberships,
            new InMemoryTransactionManager()
        );
        $deleteOrganization = new DeleteOrganizationUseCase($organizations, $authorization);
        $listOrganizations = new ListOrganizationsUseCase($organizations, $memberships, $authorization);

        $organization = $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A'));

        $deleteOrganization->execute(new DeleteOrganizationCommand($organization->id, 1));

        $this->assertSame([], $listOrganizations->execute(new ListOrganizationsForPersonQuery(1)));
    }
}
