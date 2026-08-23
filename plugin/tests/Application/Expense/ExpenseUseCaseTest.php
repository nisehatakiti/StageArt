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
use StageArt\Application\Expense\GetExpenseQuery;
use StageArt\Application\Expense\GetExpenseUseCase;
use StageArt\Application\Expense\UpdateExpenseCommand;
use StageArt\Application\Expense\UpdateExpenseUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
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

    private CreateAccountUseCase $createAccount;
    private CreateExpenseUseCase $createExpense;
    private GetExpenseUseCase $getExpense;
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

        $orgAuth = new OrganizationAuthorizationService($this->people, $this->memberships);
        $prodAuth = new ProductionAuthorizationService(
            $orgAuth,
            new InMemoryProductionDelegateRepository(),
            $this->participants
        );
        $resolver = new ProductionOrganizationResolver($this->projects);
        $transactions = new InMemoryTransactionManager();
        $lineFactory = new ExpenseLineFactory($this->accounts);

        $this->createAccount = new CreateAccountUseCase($this->accounts, $this->organizations, $orgAuth);
        $this->createExpense = new CreateExpenseUseCase($this->expenses, $this->productions, $prodAuth, $resolver, $lineFactory, $transactions);
        $this->getExpense = new GetExpenseUseCase($this->expenses, $this->productions, $prodAuth);
        $this->updateExpense = new UpdateExpenseUseCase($this->expenses, $this->productions, $prodAuth, $resolver, $lineFactory, $transactions);
        $this->confirmExpense = new ConfirmExpenseUseCase(
            $this->expenses,
            $this->productions,
            $this->accounts,
            $this->journalEntries,
            $prodAuth,
            $resolver,
            $transactions
        );
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

    public function test_get_expense_is_visible_to_any_production_member(): void
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

    public function test_primary_manager_can_confirm_expense_and_it_generates_a_balanced_journal_entry(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId, $payableAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $created = $this->createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 30000],
        ]));

        $result = $this->confirmExpense->execute(new ConfirmExpenseCommand($created->id, 1, $payableAccountId));

        $this->assertSame('CONFIRMED', $result->status);

        $entries = $this->journalEntries->findByProductionId($production->id());
        $this->assertCount(1, $entries);
        $this->assertSame('ExpenseConfirmed', $entries[0]->sourceEventType());
        $this->assertSame($created->id, $entries[0]->sourceEventId());

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($entries[0]->lines() as $line) {
            if ($line->debitCredit()->toString() === 'DEBIT') {
                $totalDebit += $line->amount();
            } else {
                $totalCredit += $line->amount();
            }
        }
        $this->assertSame(30000, $totalDebit);
        $this->assertSame(30000, $totalCredit);
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
}
