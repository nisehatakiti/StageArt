<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationOwnerInvariantViolatedException;
use StageArt\Application\Organization\OwnerTransferCommand;
use StageArt\Application\Organization\OwnerTransferTargetNotEligibleException;
use StageArt\Application\Organization\OwnerTransferUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;
use StageArt\Tests\Support\SaveFailingMembershipRepository;

final class OwnerTransferUseCaseTest extends TestCase
{
    /**
     * @return array{0: InMemoryPersonRepository, 1: InMemoryMembershipRepository, 2: InMemoryUserAccountRepository, 3: OrganizationAuthorizationService}
     */
    private function makeContext(): array
    {
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $userAccounts = new InMemoryUserAccountRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        return [$people, $memberships, $userAccounts, $authorization];
    }

    public function test_successful_transfer_swaps_owner_and_member_roles(): void
    {
        [$people, $memberships, $userAccounts, $authorization] = $this->makeContext();
        $organizationId = OrganizationId::generate();

        $ownerPerson = Person::create(1);
        $people->save($ownerPerson);
        $memberships->save(Membership::createOwnerMembership($organizationId, $ownerPerson->id()));

        $newOwnerPerson = Person::create(2);
        $people->save($newOwnerPerson);
        $memberships->save(Membership::create($organizationId, $newOwnerPerson->id(), RoleKey::member()));
        $userAccounts->save(UserAccount::create($newOwnerPerson->id()));

        $useCase = new OwnerTransferUseCase($memberships, $userAccounts, $authorization, new InMemoryTransactionManager());

        $useCase->execute(new OwnerTransferCommand($organizationId->toString(), 1, $newOwnerPerson->id()->toString()));

        $oldOwnerMembership = $memberships->findByOrganizationAndPerson($organizationId, $ownerPerson->id());
        $newOwnerMembership = $memberships->findByOrganizationAndPerson($organizationId, $newOwnerPerson->id());

        $this->assertSame(RoleKey::MEMBER, $oldOwnerMembership->roleKey()->toString());
        $this->assertSame(RoleKey::OWNER, $newOwnerMembership->roleKey()->toString());

        $allMemberships = $memberships->findByOrganizationId($organizationId);
        $owners = array_filter($allMemberships, static fn (Membership $m): bool => $m->roleKey()->toString() === RoleKey::OWNER);
        $this->assertCount(1, $owners);
    }

    public function test_executor_who_is_not_owner_is_denied(): void
    {
        [$people, $memberships, $userAccounts, $authorization] = $this->makeContext();
        $organizationId = OrganizationId::generate();

        $ownerPerson = Person::create(1);
        $people->save($ownerPerson);
        $memberships->save(Membership::createOwnerMembership($organizationId, $ownerPerson->id()));

        $memberPerson = Person::create(2);
        $people->save($memberPerson);
        $memberships->save(Membership::create($organizationId, $memberPerson->id(), RoleKey::member()));
        $userAccounts->save(UserAccount::create($memberPerson->id()));

        $targetPerson = Person::create(3);
        $people->save($targetPerson);
        $memberships->save(Membership::create($organizationId, $targetPerson->id(), RoleKey::member()));
        $userAccounts->save(UserAccount::create($targetPerson->id()));

        $useCase = new OwnerTransferUseCase($memberships, $userAccounts, $authorization, new InMemoryTransactionManager());

        $this->expectException(OrganizationAccessDeniedException::class);

        // WordPress user 2 (a plain MEMBER, not the Owner) attempts the transfer.
        $useCase->execute(new OwnerTransferCommand($organizationId->toString(), 2, $targetPerson->id()->toString()));
    }

    public function test_new_owner_without_a_membership_is_rejected(): void
    {
        [$people, $memberships, $userAccounts, $authorization] = $this->makeContext();
        $organizationId = OrganizationId::generate();

        $ownerPerson = Person::create(1);
        $people->save($ownerPerson);
        $memberships->save(Membership::createOwnerMembership($organizationId, $ownerPerson->id()));

        $outsiderPerson = Person::create(2);
        $people->save($outsiderPerson);
        $userAccounts->save(UserAccount::create($outsiderPerson->id()));
        // Deliberately no Membership saved for $outsiderPerson in this Organization.

        $useCase = new OwnerTransferUseCase($memberships, $userAccounts, $authorization, new InMemoryTransactionManager());

        $this->expectException(OwnerTransferTargetNotEligibleException::class);

        $useCase->execute(new OwnerTransferCommand($organizationId->toString(), 1, $outsiderPerson->id()->toString()));
    }

    public function test_new_owner_without_a_user_account_is_rejected(): void
    {
        [$people, $memberships, $userAccounts, $authorization] = $this->makeContext();
        $organizationId = OrganizationId::generate();

        $ownerPerson = Person::create(1);
        $people->save($ownerPerson);
        $memberships->save(Membership::createOwnerMembership($organizationId, $ownerPerson->id()));

        $memberPerson = Person::create(2);
        $people->save($memberPerson);
        $memberships->save(Membership::create($organizationId, $memberPerson->id(), RoleKey::member()));
        // Deliberately no UserAccount for $memberPerson.

        $useCase = new OwnerTransferUseCase($memberships, $userAccounts, $authorization, new InMemoryTransactionManager());

        $this->expectException(OwnerTransferTargetNotEligibleException::class);

        $useCase->execute(new OwnerTransferCommand($organizationId->toString(), 1, $memberPerson->id()->toString()));
    }

    public function test_new_owner_with_a_suspended_user_account_is_rejected(): void
    {
        [$people, $memberships, $userAccounts, $authorization] = $this->makeContext();
        $organizationId = OrganizationId::generate();

        $ownerPerson = Person::create(1);
        $people->save($ownerPerson);
        $memberships->save(Membership::createOwnerMembership($organizationId, $ownerPerson->id()));

        $memberPerson = Person::create(2);
        $people->save($memberPerson);
        $memberships->save(Membership::create($organizationId, $memberPerson->id(), RoleKey::member()));

        $suspendedAccount = UserAccount::create($memberPerson->id());
        $suspendedAccount->suspend();
        $userAccounts->save($suspendedAccount);

        $useCase = new OwnerTransferUseCase($memberships, $userAccounts, $authorization, new InMemoryTransactionManager());

        $this->expectException(OwnerTransferTargetNotEligibleException::class);

        $useCase->execute(new OwnerTransferCommand($organizationId->toString(), 1, $memberPerson->id()->toString()));
    }

    public function test_transfer_to_self_is_rejected(): void
    {
        [$people, $memberships, $userAccounts, $authorization] = $this->makeContext();
        $organizationId = OrganizationId::generate();

        $ownerPerson = Person::create(1);
        $people->save($ownerPerson);
        $memberships->save(Membership::createOwnerMembership($organizationId, $ownerPerson->id()));
        $userAccounts->save(UserAccount::create($ownerPerson->id()));

        $useCase = new OwnerTransferUseCase($memberships, $userAccounts, $authorization, new InMemoryTransactionManager());

        $this->expectException(OwnerTransferTargetNotEligibleException::class);

        $useCase->execute(new OwnerTransferCommand($organizationId->toString(), 1, $ownerPerson->id()->toString()));
    }

    public function test_zero_owners_is_an_invariant_violation_surfaced_as_access_denied(): void
    {
        [$people, $memberships, $userAccounts, $authorization] = $this->makeContext();
        $organizationId = OrganizationId::generate();

        // Corrupted state, constructed directly: an Organization with only
        // MEMBER Memberships and no OWNER at all. Nothing in ordinary use
        // (Create/Update/Archive/Transfer) can produce this; it stands in for
        // data corruption the Use Case must refuse to silently fix.
        $memberPerson = Person::create(1);
        $people->save($memberPerson);
        $memberships->save(Membership::create($organizationId, $memberPerson->id(), RoleKey::member()));

        $targetPerson = Person::create(2);
        $people->save($targetPerson);
        $memberships->save(Membership::create($organizationId, $targetPerson->id(), RoleKey::member()));
        $userAccounts->save(UserAccount::create($targetPerson->id()));

        $useCase = new OwnerTransferUseCase($memberships, $userAccounts, $authorization, new InMemoryTransactionManager());

        // With zero Owners, no WordPress user can ever pass the initial
        // hasRole($executor, $organizationId, [OWNER]) gate in execute(), so
        // the "count !== 1" check inside the transaction (which would raise
        // OrganizationOwnerInvariantViolatedException) is never reached via
        // the public API in this scenario. This is correct: the executor
        // gate is itself proof the invariant is already broken. The 0-branch
        // of the in-transaction check remains as a defensive safeguard for a
        // narrower race (executor legitimately holds OWNER at the hasRole
        // check, but the row disappears before the transaction reads it),
        // not something reachable from a clean start.
        $this->expectException(OrganizationAccessDeniedException::class);

        $useCase->execute(new OwnerTransferCommand($organizationId->toString(), 1, $targetPerson->id()->toString()));
    }

    public function test_multiple_owners_is_an_invariant_violation_and_is_not_auto_corrected(): void
    {
        [$people, $memberships, $userAccounts, $authorization] = $this->makeContext();
        $organizationId = OrganizationId::generate();

        $ownerOne = Person::create(1);
        $people->save($ownerOne);
        $memberships->save(Membership::createOwnerMembership($organizationId, $ownerOne->id()));

        // Corrupted state, constructed directly: a second OWNER Membership in
        // the same Organization. Nothing in ordinary use can produce this.
        $ownerTwo = Person::create(2);
        $people->save($ownerTwo);
        $memberships->save(Membership::createOwnerMembership($organizationId, $ownerTwo->id()));

        $targetPerson = Person::create(3);
        $people->save($targetPerson);
        $memberships->save(Membership::create($organizationId, $targetPerson->id(), RoleKey::member()));
        $userAccounts->save(UserAccount::create($targetPerson->id()));

        $useCase = new OwnerTransferUseCase($memberships, $userAccounts, $authorization, new InMemoryTransactionManager());

        $this->expectException(OrganizationOwnerInvariantViolatedException::class);

        // Executor is one of the two (illegitimate) Owners, so hasRole() passes
        // and the invariant check inside the transaction is what actually fails.
        $useCase->execute(new OwnerTransferCommand($organizationId->toString(), 1, $targetPerson->id()->toString()));
    }

    public function test_membership_save_failure_propagates_instead_of_being_swallowed(): void
    {
        [$people, $realMemberships, $userAccounts, $authorization] = $this->makeContext();
        $organizationId = OrganizationId::generate();

        $ownerPerson = Person::create(1);
        $people->save($ownerPerson);
        $realMemberships->save(Membership::createOwnerMembership($organizationId, $ownerPerson->id()));

        $newOwnerPerson = Person::create(2);
        $people->save($newOwnerPerson);
        $realMemberships->save(Membership::create($organizationId, $newOwnerPerson->id(), RoleKey::member()));
        $userAccounts->save(UserAccount::create($newOwnerPerson->id()));

        // Recompute authorization against the same underlying data via a
        // failing-on-save decorator, so hasRole()/findByOrganizationId() still
        // see the real data but the final save() calls fail.
        $failingMemberships = new SaveFailingMembershipRepository($realMemberships);
        $failingAuthorization = new OrganizationAuthorizationService($people, $failingMemberships);

        $useCase = new OwnerTransferUseCase(
            $failingMemberships,
            $userAccounts,
            $failingAuthorization,
            new InMemoryTransactionManager()
        );

        $this->expectException(RuntimeException::class);

        $useCase->execute(new OwnerTransferCommand($organizationId->toString(), 1, $newOwnerPerson->id()->toString()));

        // As with CreateOrganizationUseCaseTest's equivalent case, full atomicity
        // (that neither Membership row changes on a real database when the
        // second save() fails) is verified against WordPressTransactionManager
        // on ConoHa, not here.
    }
}
