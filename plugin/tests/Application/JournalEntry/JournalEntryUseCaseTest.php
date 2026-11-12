<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\JournalEntry;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Account\CreateAccountCommand;
use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Expense\ConfirmExpenseCommand;
use StageArt\Application\Expense\ConfirmExpenseUseCase;
use StageArt\Application\Expense\CreateExpenseCommand;
use StageArt\Application\Expense\CreateExpenseUseCase;
use StageArt\Application\Expense\ExpenseLineFactory;
use StageArt\Application\Expense\ExpenseOperationRetiredException;
use StageArt\Application\JournalEntry\JournalEntryAccessDeniedException;
use StageArt\Application\JournalEntry\ListJournalEntriesUseCase;
use StageArt\Application\JournalEntry\ListProductionJournalEntriesQuery;
use StageArt\Application\JournalEntry\PostJournalEntryCommand;
use StageArt\Application\JournalEntry\PostJournalEntryUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
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

final class JournalEntryUseCaseTest extends TestCase
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
    private ConfirmExpenseUseCase $confirmExpense;
    private PostJournalEntryUseCase $postJournalEntry;
    private ListJournalEntriesUseCase $listJournalEntries;

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

        $productionContext = new CoreProductionContextAdapter($this->productions, $resolver);
        $identity = new CoreIdentityAdapter($this->people);
        $authorizationContract = new CoreAuthorizationAdapter($prodAuth, $this->productions, $this->people);
        $membershipContract = new CoreMembershipAdapter(
            $this->participants,
            $this->productions,
            $this->people,
            $prodAuth
        );

        $organizationContext = new CoreOrganizationContextAdapter($this->organizations);
        $this->createAccount = new CreateAccountUseCase($this->accounts, $organizationContext, $identity, $authorizationContract);
        $this->createExpense = new CreateExpenseUseCase(
            $this->expenses,
            $productionContext,
            $membershipContract,
            $identity,
            new ExpenseLineFactory($this->accounts),
            $transactions
        );
        $this->confirmExpense = new ConfirmExpenseUseCase($this->expenses, $identity, $authorizationContract);
        $this->postJournalEntry = new PostJournalEntryUseCase($this->journalEntries, $productionContext, $identity, $authorizationContract, $transactions);
        $this->listJournalEntries = new ListJournalEntriesUseCase($this->journalEntries, $productionContext, $identity, $authorizationContract);
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

        // The Expenses below are created by this member and confirmed by
        // the PrimaryManager: nobody confirms their own Expense
        // (自己承認の禁止, ExpenseApprovalWorkflow.md).
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

    /**
     * A DRAFT Journal Entry like the ones the retired Confirm step left
     * behind ("ExpenseConfirmed"); PostJournalEntryUseCase still posts
     * these.
     */
    private function givenLegacyDraftJournalEntry(Production $production, string $expenseAccountId, string $payableAccountId): JournalEntry
    {
        $entry = JournalEntry::create(
            $this->projects->findById($production->projectId())->organizationId(),
            $production->id(),
            new DateTimeImmutable(),
            'Expense Confirmed (legacy)',
            [
                JournalEntryLine::create(AccountId::fromString($expenseAccountId), DebitCredit::debit(), 45000),
                JournalEntryLine::create(AccountId::fromString($payableAccountId), DebitCredit::credit(), 45000),
            ],
            $production->primaryManagerPersonId(),
            'ExpenseConfirmed',
            ExpenseId::generate()->toString()
        );
        $this->journalEntries->save($entry);

        return $entry;
    }

    public function test_the_retired_confirm_step_creates_no_journal_entry(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId, $payableAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $expense = $this->createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 45000],
        ]));

        try {
            $this->confirmExpense->execute(new ConfirmExpenseCommand($expense->id, 1, $payableAccountId));
            $this->fail('Confirm is retired and must be refused.');
        } catch (ExpenseOperationRetiredException $exception) {
            $this->assertSame([], $this->listJournalEntries->execute(new ListProductionJournalEntriesQuery($production->id()->toString(), 1)));
        }
    }

    public function test_post_transitions_draft_entry_to_posted(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId, $payableAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $entry = $this->givenLegacyDraftJournalEntry($production, $expenseAccountId, $payableAccountId);

        $result = $this->postJournalEntry->execute(new PostJournalEntryCommand($entry->id()->toString(), 1));

        $this->assertSame('POSTED', $result->status);
    }

    public function test_post_twice_is_rejected_at_use_case_level(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId, $payableAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $entry = $this->givenLegacyDraftJournalEntry($production, $expenseAccountId, $payableAccountId);
        $this->postJournalEntry->execute(new PostJournalEntryCommand($entry->id()->toString(), 1));

        $this->expectException(InvalidArgumentException::class);

        $this->postJournalEntry->execute(new PostJournalEntryCommand($entry->id()->toString(), 1));
    }

    public function test_non_manager_member_cannot_post_journal_entry(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        [$expenseAccountId, $payableAccountId] = $this->createExpenseAndPayableAccounts($organization);

        $entry = $this->givenLegacyDraftJournalEntry($production, $expenseAccountId, $payableAccountId);

        $this->expectException(JournalEntryAccessDeniedException::class);

        $this->postJournalEntry->execute(new PostJournalEntryCommand($entry->id()->toString(), 2));
    }

    public function test_non_manager_member_cannot_list_journal_entries(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();

        $this->expectException(JournalEntryAccessDeniedException::class);

        $this->listJournalEntries->execute(new ListProductionJournalEntriesQuery($production->id()->toString(), 2));
    }
}
