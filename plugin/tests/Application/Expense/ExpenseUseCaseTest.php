<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Expense;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Account\CreateAccountCommand;
use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Expense\ConfirmExpenseCommand;
use StageArt\Application\Expense\ConfirmExpenseUseCase;
use StageArt\Application\Expense\CreateExpenseCommand;
use StageArt\Application\Expense\CreateExpenseUseCase;
use StageArt\Application\Expense\ExpenseAccessDeniedException;
use StageArt\Application\Expense\ExpenseLineFactory;
use StageArt\Application\Expense\ExpenseOperationRetiredException;
use StageArt\Application\Expense\GetExpenseQuery;
use StageArt\Application\Expense\GetExpenseUseCase;
use StageArt\Application\Expense\ListExpensesUseCase;
use StageArt\Application\Expense\ListProductionExpensesQuery;
use StageArt\Application\Expense\UpdateExpenseCommand;
use StageArt\Application\Expense\UpdateExpenseUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryExpenseRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class ExpenseUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProjectRepository $projects;
    private InMemoryProductionRepository $productions;
    private InMemoryAccountRepository $accounts;
    private InMemoryExpenseRepository $expenses;
    private InMemoryJournalEntryRepository $journalEntries;
    private InMemoryParticipantRepository $participants;
    private InMemoryProductionDelegateRepository $delegates;

    private CreateAccountUseCase $createAccount;
    private CreateExpenseUseCase $createExpense;
    private GetExpenseUseCase $getExpense;
    private ListExpensesUseCase $listExpenses;
    private UpdateExpenseUseCase $updateExpense;
    private ConfirmExpenseUseCase $confirmExpense;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->expenses = new InMemoryExpenseRepository();
        $this->journalEntries = new InMemoryJournalEntryRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();

        $orgAuth = new OrganizationAuthorizationService($this->people, $this->memberships);
        $prodAuth = new ProductionAuthorizationService(
            $orgAuth,
            $this->delegates,
            $this->participants
        );
        $resolver = new ProductionOrganizationResolver($this->projects);
        $transactions = new InMemoryTransactionManager();
        $lineFactory = new ExpenseLineFactory($this->accounts);

        $productionContext = new CoreProductionContextAdapter($this->productions, $resolver);
        $identity = new CoreIdentityAdapter($this->people);
        $authorizationContract = new CoreAuthorizationAdapter($prodAuth, $this->productions, $this->people);
        $membershipContract = new CoreMembershipAdapter($this->participants, $this->productions, $this->people, $prodAuth);

        $organizationContext = new CoreOrganizationContextAdapter($this->organizations);
        $this->createAccount = new CreateAccountUseCase($this->accounts, $organizationContext, $identity, $authorizationContract);
        $this->createExpense = new CreateExpenseUseCase($this->expenses, $productionContext, $membershipContract, $identity, $lineFactory, $transactions);
        $this->getExpense = new GetExpenseUseCase($this->expenses, $identity, $membershipContract, $authorizationContract);
        $this->listExpenses = new ListExpensesUseCase($this->expenses, $productionContext, $membershipContract, $identity, $authorizationContract);
        $this->updateExpense = new UpdateExpenseUseCase($this->expenses, $productionContext, $identity, $authorizationContract, $lineFactory, $transactions);
        $this->confirmExpense = new ConfirmExpenseUseCase($this->expenses, $identity, $authorizationContract);
    }

    /**
     * @return array{0: Organization, 1: Production, 2: Person, 3: Person} Organization, Production, PrimaryManager, non-manager Member
     */
    private function givenProductionWithPrimaryManagerAndMember(): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $manager = Person::create(1);
        $this->people->save($manager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $manager->id()));

        $member = Person::create(2);
        $this->people->save($member);
        $this->memberships->save(Membership::create($organization->id(), $member->id(), RoleKey::member()));

        $project = Project::create($organization->id(), 'Spring Season');
        $this->projects->save($project);

        $production = Production::create($project->id(), new ProductionName('Autumn Play'), $manager->id());
        $this->productions->save($production);

        // isProductionMember() requires an ACTIVE, Person-subject
        // Participant on this specific Production - an Organization
        // MEMBER Membership alone does not qualify (see
        // ProductionAuthorizationService::isProductionMember()'s
        // docblock), so the non-manager fixture is also added as a Cast
        // Participant here.
        $this->participants->save(Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $member->id()->toString(),
            ParticipantType::cast()
        ));

        return [$organization, $production, $manager, $member];
    }

    /**
     * @return array{0: string, 1: string} [expenseAccountId, payableAccountId]
     */
    private function createExpenseAndPayableAccounts(Organization $organization): array
    {
        $expenseAccountId = $this->createAccount->execute(
            new CreateAccountCommand(1, $organization->id()->toString(), '会場費', AccountType::EXPENSE)
        )->id;
        $payableAccountId = $this->createAccount->execute(
            new CreateAccountCommand(1, $organization->id()->toString(), '未払金', AccountType::LIABILITY)
        )->id;

        return [$expenseAccountId, $payableAccountId];
    }

    public function test_any_production_member_can_create_expense(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $result = $this->createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 12000, 'description' => '交通費'],
        ]));

        $this->assertSame('DRAFT', $result->status);
        $this->assertSame(12000, $result->totalAmount);
    }

    public function test_primary_manager_can_view_a_members_expense(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $created = $this->createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 12000],
        ]));

        $result = $this->getExpense->execute(new GetExpenseQuery($created->id, 1));

        $this->assertSame($created->id, $result->id);
    }

    public function test_update_expense_allowed_for_own_draft_creator(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $created = $this->createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 12000],
        ]));

        $result = $this->updateExpense->execute(new UpdateExpenseCommand($created->id, 2, [
            ['account_id' => $expenseAccountId, 'amount' => 15000],
        ]));

        $this->assertSame(15000, $result->totalAmount);
    }

    public function test_update_expense_rejected_for_non_creator_non_manager(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $created = $this->createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 12000],
        ]));

        $outsider = Person::create(3);
        $this->people->save($outsider);
        $this->memberships->save(Membership::create($organization->id(), $outsider->id(), RoleKey::member()));

        $this->expectException(ExpenseAccessDeniedException::class);

        $this->updateExpense->execute(new UpdateExpenseCommand($created->id, 3, [
            ['account_id' => $expenseAccountId, 'amount' => 99999],
        ]));
    }

    /**
     * 修正8: Confirm is retired in favour of submit -> approve. The
     * endpoint stays but refuses, and changes nothing.
     */
    public function test_confirm_is_refused_even_for_the_primary_manager_and_changes_nothing(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId, $payableAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $created = $this->createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 30000],
        ]));

        try {
            $this->confirmExpense->execute(new ConfirmExpenseCommand($created->id, 1, $payableAccountId));
            $this->fail('Confirm must be refused.');
        } catch (ExpenseOperationRetiredException $exception) {
            $this->assertSame('DRAFT', $this->getExpense->execute(new GetExpenseQuery($created->id, 1))->status);
            $this->assertSame([], $this->journalEntries->findByProductionId($production->id()));
        }
    }

    public function test_non_manager_member_cannot_confirm_expense(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId, $payableAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $created = $this->createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 30000],
        ]));

        $this->expectException(ExpenseAccessDeniedException::class);

        $this->confirmExpense->execute(new ConfirmExpenseCommand($created->id, 2, $payableAccountId));
    }

    /**
     * Adds an Organization MEMBER with the given WordPress user id who is
     * also on this Production - either as a Cast Participant (no Role) or
     * as an ACTIVE Delegate with the given Role.
     */
    private function givenProductionPerson(Organization $organization, Production $production, int $wordPressUserId, ?RoleKey $delegateRole = null): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->memberships->save(Membership::create($organization->id(), $person->id(), RoleKey::member()));

        if ($delegateRole === null) {
            $this->participants->save(Participant::create(
                $production->id(),
                ParticipantSubjectType::person(),
                $person->id()->toString(),
                ParticipantType::cast()
            ));
        } else {
            $this->delegates->save(ProductionDelegate::create($production->id(), $person->id(), $delegateRole, $production->primaryManagerPersonId()));
        }

        return $person;
    }

    /**
     * @return array{0: Production, 1: string, 2: string} [Production, member 2's Expense id, member 3's Expense id]
     */
    private function givenTwoMembersExpenses(): array
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId] = $this->createExpenseAndPayableAccounts($organization);
        $this->givenProductionPerson($organization, $production, 3);
        $this->givenProductionPerson($organization, $production, 4, RoleKey::accountingManager());
        $this->givenProductionPerson($organization, $production, 5, RoleKey::ticketManager());

        $own = $this->createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 1200],
        ]))->id;
        $others = $this->createExpense->execute(new CreateExpenseCommand(3, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 3400],
        ]))->id;

        return [$production, $own, $others];
    }

    /**
     * @return string[]
     */
    private function listedIds(Production $production, int $wordPressUserId): array
    {
        $ids = array_map(
            static fn ($result): string => $result->id,
            $this->listExpenses->execute(new ListProductionExpensesQuery($production->id()->toString(), $wordPressUserId))
        );
        sort($ids);

        return $ids;
    }

    public function test_primary_manager_and_accounting_manager_list_every_expense(): void
    {
        [$production, $own, $others] = $this->givenTwoMembersExpenses();
        $all = [$own, $others];
        sort($all);

        $this->assertSame($all, $this->listedIds($production, 1));
        $this->assertSame($all, $this->listedIds($production, 4));
    }

    /**
     * A 代理人 approves Expenses (`Expense.Approve`), so it lists and reads
     * every Expense of the Production like the PrimaryManager and the
     * 会計管理者 do. Holding only one of its two Roles, or one of them no
     * longer ACTIVE, leaves a Delegate with its own Expenses only.
     */
    public function test_a_proxy_manager_sees_every_expense_but_a_partial_proxy_only_their_own(): void
    {
        [$production, $own, $others] = $this->givenTwoMembersExpenses();
        $organization = $this->organizations->findById($this->projects->findById($production->projectId())->organizationId());
        $all = [$own, $others];
        sort($all);

        $proxy = $this->givenProductionPerson($organization, $production, 8, RoleKey::participantManager());
        $this->delegates->save(ProductionDelegate::create($production->id(), $proxy->id(), RoleKey::rehearsalManager(), $production->primaryManagerPersonId()));
        $this->givenProductionPerson($organization, $production, 9, RoleKey::participantManager());
        $former = $this->givenProductionPerson($organization, $production, 10, RoleKey::participantManager());
        $rehearsal = ProductionDelegate::create($production->id(), $former->id(), RoleKey::rehearsalManager(), $production->primaryManagerPersonId());
        $rehearsal->deactivate($production->primaryManagerPersonId());
        $this->delegates->save($rehearsal);

        $this->assertSame($all, $this->listedIds($production, 8));
        $this->assertSame($others, $this->getExpense->execute(new GetExpenseQuery($others, 8))->id);

        foreach ([9, 10] as $partial) {
            $this->assertSame([], $this->listedIds($production, $partial), "user {$partial}");

            try {
                $this->getExpense->execute(new GetExpenseQuery($others, $partial));
                $this->fail("User {$partial} must not read another member's Expense.");
            } catch (ExpenseAccessDeniedException $exception) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_general_member_lists_only_their_own_expenses(): void
    {
        [$production, $own, $others] = $this->givenTwoMembersExpenses();

        $this->assertSame([$own], $this->listedIds($production, 2));
        $this->assertSame([$others], $this->listedIds($production, 3));
    }

    public function test_delegate_without_accounting_role_lists_only_their_own_expenses(): void
    {
        [$production] = $this->givenTwoMembersExpenses();

        $this->assertSame([], $this->listedIds($production, 5));
    }

    public function test_outsider_cannot_list_expenses(): void
    {
        [$production] = $this->givenTwoMembersExpenses();
        $outsider = Person::create(6);
        $this->people->save($outsider);

        $this->expectException(ExpenseAccessDeniedException::class);

        $this->listExpenses->execute(new ListProductionExpensesQuery($production->id()->toString(), 6));
    }

    public function test_expense_list_of_another_production_is_not_reachable_through_this_productions_role(): void
    {
        [$production] = $this->givenTwoMembersExpenses();
        $other = Production::create($production->projectId(), new ProductionName('Winter Play'), $production->primaryManagerPersonId());
        $this->productions->save($other);

        // Person 4 is ACCOUNTING_MANAGER of $production only.
        $this->expectException(ExpenseAccessDeniedException::class);

        $this->listExpenses->execute(new ListProductionExpensesQuery($other->id()->toString(), 4));
    }

    public function test_creator_can_view_their_own_expense(): void
    {
        [, $own] = $this->givenTwoMembersExpenses();

        $this->assertSame($own, $this->getExpense->execute(new GetExpenseQuery($own, 2))->id);
    }

    public function test_accounting_manager_can_view_any_expense(): void
    {
        [, $own, $others] = $this->givenTwoMembersExpenses();

        $this->assertSame($own, $this->getExpense->execute(new GetExpenseQuery($own, 4))->id);
        $this->assertSame($others, $this->getExpense->execute(new GetExpenseQuery($others, 4))->id);
    }

    public function test_general_member_cannot_view_another_members_expense_by_id(): void
    {
        [, , $others] = $this->givenTwoMembersExpenses();

        $this->expectException(ExpenseAccessDeniedException::class);

        $this->getExpense->execute(new GetExpenseQuery($others, 2));
    }

    public function test_delegate_without_accounting_role_cannot_view_another_members_expense(): void
    {
        [, $own] = $this->givenTwoMembersExpenses();

        $this->expectException(ExpenseAccessDeniedException::class);

        $this->getExpense->execute(new GetExpenseQuery($own, 5));
    }

    public function test_creator_who_left_the_production_cannot_view_their_expense(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId] = $this->createExpenseAndPayableAccounts($organization);
        $creator = $this->givenProductionPerson($organization, $production, 3, RoleKey::ticketManager());
        $created = $this->createExpense->execute(new CreateExpenseCommand(3, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 500],
        ]))->id;

        $delegate = $this->delegates->findByProductionId($production->id())[0];
        $delegate->deactivate($production->primaryManagerPersonId());
        $this->delegates->save($delegate);
        $this->assertTrue($creator->id()->equals($delegate->personId()));

        $this->expectException(ExpenseAccessDeniedException::class);

        $this->getExpense->execute(new GetExpenseQuery($created, 3));
    }
}
