<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Expense;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Account\CreateAccountCommand;
use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Accounting\StandardPayableAccountResolver;
use StageArt\Application\Expense\ApplicantExpenseTransitionUseCase;
use StageArt\Application\Expense\ApproveExpenseCommand;
use StageArt\Application\Expense\ApproveExpenseUseCase;
use StageArt\Application\Expense\CreateExpenseCommand;
use StageArt\Application\Expense\CreateExpenseUseCase;
use StageArt\Application\Expense\ExpenseAccessDeniedException;
use StageArt\Application\Expense\ExpenseAccountingDisabledException;
use StageArt\Application\Expense\ExpenseLineFactory;
use StageArt\Application\Expense\ExpenseTransitionCommand;
use StageArt\Application\Expense\ExpenseWorkflowNotifier;
use StageArt\Application\Expense\RejectExpenseCommand;
use StageArt\Application\Expense\RejectExpenseUseCase;
use StageArt\Application\Expense\UpdateExpenseCommand;
use StageArt\Application\Expense\UpdateExpenseUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\ProductionAccounting\GetProductionAccountingSummaryQuery;
use StageArt\Application\ProductionAccounting\GetProductionAccountingSummaryUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionCapabilityDirectoryAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseReview;
use StageArt\Domain\Expense\ExpenseStateConflictException;
use StageArt\Domain\Expense\ExpenseStatus;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryBudgetRepository;
use StageArt\Tests\Support\InMemoryExpenseRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\RecordingNotificationContract;
use StageArt\Tests\Support\RollbackingTransactionManager;

/**
 * 修正8 経費ワークフロー, Application level: submit / withdraw / resubmit
 * by the applicant, approve (posting) and reject by an approver, the
 * revision check, double-posting protection, rollback on a failed write,
 * post-commit notifications, and who may do what.
 *
 * WordPress user ids: 1 = PrimaryManager, 2 = applicant (Cast
 * Participant), 3 = another Cast Participant, 4 = ACCOUNTING_MANAGER,
 * 5 = TICKET_MANAGER, 6 = Organization member outside the Production,
 * 7 = a second ACCOUNTING_MANAGER, 8 = 代理人 (ACTIVE PARTICIPANT_MANAGER
 * + REHEARSAL_MANAGER), 9 = PARTICIPANT_MANAGER only, 10 = a former 代理人
 * whose REHEARSAL_MANAGER Role was deactivated.
 *
 * Approving and rejecting need `Expense.Approve` - PrimaryManager,
 * 会計管理者 and 代理人 (StageArt 統合・会計仕様の確定事項 2026-10-28 §2) -
 * and the same people are the approvers the submission is announced to.
 */
final class ExpenseWorkflowUseCaseTest extends TestCase
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
    private RollbackingTransactionManager $transactions;
    private RecordingNotificationContract $notifications;

    private CreateAccountUseCase $createAccount;
    private CreateExpenseUseCase $createExpense;
    private UpdateExpenseUseCase $updateExpense;
    private ApplicantExpenseTransitionUseCase $applicantTransitions;
    private ApproveExpenseUseCase $approveExpense;
    private RejectExpenseUseCase $rejectExpense;
    private GetProductionAccountingSummaryUseCase $summary;

    private Organization $organization;
    private Production $production;
    private string $expenseAccountId;
    /** @var array<int, Person> */
    private array $person = [];

    protected function setUp(): void
    {
        $this->build(true);
    }

    private function build(bool $accountingEnabled): void
    {
        $this->person = [];
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
        $this->transactions = new RollbackingTransactionManager($this->expenses, $this->journalEntries);
        $this->notifications = new RecordingNotificationContract($this->transactions);

        $orgAuth = new OrganizationAuthorizationService($this->people, $this->memberships);
        $prodAuth = new ProductionAuthorizationService($orgAuth, $this->delegates, $this->participants);
        $resolver = new ProductionOrganizationResolver($this->projects);
        $lineFactory = new ExpenseLineFactory($this->accounts);

        $productionContext = new CoreProductionContextAdapter($this->productions, $resolver);
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($prodAuth, $this->productions, $this->people);
        $membership = new CoreMembershipAdapter($this->participants, $this->productions, $this->people, $prodAuth);
        $organizationContext = new CoreOrganizationContextAdapter($this->organizations);
        $directory = new CoreProductionCapabilityDirectoryAdapter($prodAuth, $this->productions, $this->delegates, $this->people);
        $notifier = new ExpenseWorkflowNotifier($this->notifications, $directory, $productionContext);

        $this->createAccount = new CreateAccountUseCase($this->accounts, $organizationContext, $identity, $authorization);
        $this->createExpense = new CreateExpenseUseCase($this->expenses, $productionContext, $membership, $identity, $lineFactory, $this->transactions);
        $this->updateExpense = new UpdateExpenseUseCase($this->expenses, $productionContext, $identity, $authorization, $lineFactory, $this->transactions);
        $this->applicantTransitions = new ApplicantExpenseTransitionUseCase($this->expenses, $identity, $membership, $this->transactions, $notifier);
        $this->approveExpense = new ApproveExpenseUseCase(
            $this->expenses,
            $this->journalEntries,
            $this->accounts,
            new StandardPayableAccountResolver($this->accounts),
            $productionContext,
            $organizationContext,
            $identity,
            $authorization,
            $this->transactions,
            $notifier
        );
        $this->rejectExpense = new RejectExpenseUseCase($this->expenses, $identity, $authorization, $this->transactions, $notifier);
        $this->summary = new GetProductionAccountingSummaryUseCase(
            new InMemoryBudgetRepository(),
            $this->journalEntries,
            $this->accounts,
            $productionContext,
            $membership,
            $identity
        );

        $this->givenProduction($accountingEnabled);
    }

    private function givenProduction(bool $accountingEnabled): void
    {
        $this->organization = Organization::create(new OrganizationName('Theatre Co'));

        if ($accountingEnabled) {
            $this->organization->enableAccounting();
        }

        $this->organizations->save($this->organization);

        $manager = Person::create(1);
        $this->people->save($manager);
        $this->person[1] = $manager;
        $this->memberships->save(Membership::createOwnerMembership($this->organization->id(), $manager->id()));

        $project = Project::create($this->organization->id(), 'Spring Season');
        $this->projects->save($project);

        $this->production = Production::create($project->id(), new ProductionName('Autumn Play'), $manager->id());
        $this->productions->save($this->production);

        $this->givenPerson(2);
        $this->givenPerson(3);
        $this->givenPerson(4, RoleKey::accountingManager());
        $this->givenPerson(5, RoleKey::ticketManager());
        $this->givenPerson(6, null, false);
        $this->givenPerson(7, RoleKey::accountingManager());
        $this->givenDelegatePerson(8, [RoleKey::participantManager(), RoleKey::rehearsalManager()]);
        $this->givenDelegatePerson(9, [RoleKey::participantManager()]);
        $this->givenDelegatePerson(10, [RoleKey::participantManager(), RoleKey::rehearsalManager()], true);

        $this->expenseAccountId = $this->createAccount->execute(
            new CreateAccountCommand(1, $this->organization->id()->toString(), '消耗品費', AccountType::EXPENSE)
        )->id;
    }

    private function givenPerson(int $wordPressUserId, ?RoleKey $delegateRole = null, bool $inProduction = true): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->person[$wordPressUserId] = $person;
        $this->memberships->save(Membership::create($this->organization->id(), $person->id(), RoleKey::member()));

        if (! $inProduction) {
            return $person;
        }

        if ($delegateRole === null) {
            $this->participants->save(Participant::create(
                $this->production->id(),
                ParticipantSubjectType::person(),
                $person->id()->toString(),
                ParticipantType::cast()
            ));
        } else {
            $this->delegates->save(ProductionDelegate::create($this->production->id(), $person->id(), $delegateRole, $this->production->primaryManagerPersonId()));
        }

        return $person;
    }

    /**
     * An Organization MEMBER who is an ACTIVE Delegate of this Production
     * with every given Role; with $deactivateLast the last Role is then
     * deactivated, leaving the others ACTIVE.
     *
     * @param RoleKey[] $roles
     */
    private function givenDelegatePerson(int $wordPressUserId, array $roles, bool $deactivateLast = false): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->person[$wordPressUserId] = $person;
        $this->memberships->save(Membership::create($this->organization->id(), $person->id(), RoleKey::member()));

        $delegate = null;
        foreach ($roles as $role) {
            $delegate = ProductionDelegate::create($this->production->id(), $person->id(), $role, $this->production->primaryManagerPersonId());
            $this->delegates->save($delegate);
        }

        if ($deactivateLast && $delegate !== null) {
            $delegate->deactivate($this->production->primaryManagerPersonId());
            $this->delegates->save($delegate);
        }

        return $person;
    }

    /**
     * @param int[] $amounts
     */
    private function draftBy(int $wordPressUserId, array $amounts = [5000], ?string $payerPersonId = null): string
    {
        $lines = array_map(fn (int $amount): array => ['account_id' => $this->expenseAccountId, 'amount' => $amount], $amounts);

        return $this->createExpense->execute(new CreateExpenseCommand($wordPressUserId, $this->production->id()->toString(), $lines, $payerPersonId))->id;
    }

    /**
     * @param int[] $amounts
     */
    private function submittedBy(int $wordPressUserId, array $amounts = [5000], ?string $payerPersonId = null): string
    {
        $id = $this->draftBy($wordPressUserId, $amounts, $payerPersonId);
        $this->applicantTransitions->submit(new ExpenseTransitionCommand($id, $wordPressUserId));

        return $id;
    }

    private function status(string $expenseId): string
    {
        return $this->expenses->findById(ExpenseId::fromString($expenseId))->status()->toString();
    }

    /**
     * @return JournalEntry[]
     */
    private function approvalEntries(): array
    {
        return array_values(array_filter(
            $this->journalEntries->all(),
            static fn (JournalEntry $entry): bool => $entry->sourceEventType() === ApproveExpenseUseCase::SOURCE_EVENT_TYPE
        ));
    }

    /**
     * @return string[] "wpUserId:type" for each notification sent
     */
    private function notified(): array
    {
        $byPerson = [];

        foreach ($this->person as $wordPressUserId => $person) {
            $byPerson[$person->id()->toString()] = $wordPressUserId;
        }

        return array_map(
            static fn (array $sent): string => ($byPerson[$sent['person_id']] ?? '?') . ':' . $sent['type'],
            $this->notifications->sent()
        );
    }

    // --- submit / withdraw / resubmit ------------------------------------

    public function test_creating_an_expense_does_not_submit_it_or_notify_anyone(): void
    {
        $id = $this->draftBy(2);

        $this->assertSame(ExpenseStatus::DRAFT, $this->status($id));
        $this->assertSame([], $this->notifications->sent());
    }

    public function test_the_applicant_submits_their_draft_and_every_approver_is_notified(): void
    {
        $id = $this->draftBy(2, [4200]);

        $result = $this->applicantTransitions->submit(new ExpenseTransitionCommand($id, 2));

        $this->assertSame(ExpenseStatus::SUBMITTED, $result->status);
        $this->assertSame(4200, $result->requestedAmount);
        $this->assertSame(1, $result->revision);

        $notified = $this->notified();
        sort($notified);
        $this->assertSame(['1:expense_submitted', '4:expense_submitted', '7:expense_submitted', '8:expense_submitted'], $notified);

        $payload = $this->notifications->sent()[0]['payload'];
        $this->assertSame('expense', $payload['target_type']);
        $this->assertSame($id, $payload['target_id']);
        $this->assertSame($this->production->id()->toString(), $payload['production_id']);
        $this->assertStringContainsString('Autumn Play', $payload['message']);
        $this->assertStringContainsString('4,200円', $payload['message']);
        $this->assertSame(0, $this->notifications->sentInsideTransaction());
    }

    public function test_an_approver_who_submits_their_own_expense_is_not_asked_to_approve_it(): void
    {
        $this->submittedBy(4);

        $notified = $this->notified();
        sort($notified);
        $this->assertSame(['1:expense_submitted', '7:expense_submitted', '8:expense_submitted'], $notified);
    }

    /**
     * @dataProvider othersThanTheApplicant
     */
    public function test_nobody_else_can_submit_withdraw_or_resubmit_the_applicants_expense(int $wordPressUserId): void
    {
        $draft = $this->draftBy(2);
        $submitted = $this->submittedBy(2);
        $rejected = $this->submittedBy(2);
        $this->rejectExpense->execute(new RejectExpenseCommand($rejected, 1, '不備あり'));

        $attempts = [
            'submit' => fn () => $this->applicantTransitions->submit(new ExpenseTransitionCommand($draft, $wordPressUserId)),
            'withdraw' => fn () => $this->applicantTransitions->withdraw(new ExpenseTransitionCommand($submitted, $wordPressUserId)),
            'resubmit' => fn () => $this->applicantTransitions->resubmit(new ExpenseTransitionCommand($rejected, $wordPressUserId)),
        ];

        foreach ($attempts as $name => $attempt) {
            try {
                $attempt();
                $this->fail("{$name} by user {$wordPressUserId} must be refused.");
            } catch (ExpenseAccessDeniedException $exception) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(ExpenseStatus::DRAFT, $this->status($draft));
        $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($submitted));
        $this->assertSame(ExpenseStatus::REJECTED, $this->status($rejected));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function othersThanTheApplicant(): array
    {
        return [
            'primary manager' => [1],
            'another member' => [3],
            'accounting manager' => [4],
            'ticket manager' => [5],
            'outsider' => [6],
        ];
    }

    public function test_an_applicant_who_left_the_production_can_no_longer_submit(): void
    {
        $id = $this->draftBy(5);

        foreach ($this->delegates->findByProductionId($this->production->id()) as $delegate) {
            if ($delegate->role()->equals(RoleKey::ticketManager())) {
                $delegate->deactivate($this->production->primaryManagerPersonId());
                $this->delegates->save($delegate);
            }
        }

        $this->expectException(ExpenseAccessDeniedException::class);

        $this->applicantTransitions->submit(new ExpenseTransitionCommand($id, 5));
    }

    public function test_the_applicant_withdraws_a_submitted_expense_back_to_draft(): void
    {
        $id = $this->submittedBy(2);

        $result = $this->applicantTransitions->withdraw(new ExpenseTransitionCommand($id, 2));

        $this->assertSame(ExpenseStatus::DRAFT, $result->status);
        $this->assertSame(
            [ExpenseReview::SUBMITTED, ExpenseReview::WITHDRAWN],
            array_map(static fn (array $review): string => $review['action'], $result->reviews)
        );
    }

    public function test_the_applicant_edits_a_submitted_expense_before_approval(): void
    {
        $id = $this->submittedBy(2, [5000]);

        $result = $this->updateExpense->execute(new UpdateExpenseCommand($id, 2, [
            ['account_id' => $this->expenseAccountId, 'amount' => 5500],
        ]));

        $this->assertSame(ExpenseStatus::SUBMITTED, $result->status);
        $this->assertSame(5500, $result->requestedAmount);
        $this->assertSame(2, $result->revision);
    }

    /**
     * @dataProvider othersThanTheApplicant
     */
    public function test_nobody_else_can_edit_a_submitted_expense(int $wordPressUserId): void
    {
        $id = $this->submittedBy(2, [5000]);

        try {
            $this->updateExpense->execute(new UpdateExpenseCommand($id, $wordPressUserId, [
                ['account_id' => $this->expenseAccountId, 'amount' => 1],
            ]));
            $this->fail('Only the applicant may edit a submitted Expense.');
        } catch (ExpenseAccessDeniedException $exception) {
            $this->assertSame(5000, $this->expenses->findById(ExpenseId::fromString($id))->requestedAmount());
        }
    }

    public function test_a_manager_can_still_edit_someone_elses_draft(): void
    {
        $id = $this->draftBy(2, [5000]);

        $result = $this->updateExpense->execute(new UpdateExpenseCommand($id, 4, [
            ['account_id' => $this->expenseAccountId, 'amount' => 4800],
        ]));

        $this->assertSame(4800, $result->totalAmount);
    }

    // --- approve ---------------------------------------------------------

    /**
     * @dataProvider approvers
     */
    public function test_approving_posts_one_balanced_journal_entry_against_unpaid_expenses(int $wordPressUserId): void
    {
        $id = $this->submittedBy(2, [1200, 800]);

        $result = $this->approveExpense->execute(new ApproveExpenseCommand($id, $wordPressUserId, 1));

        $this->assertSame(ExpenseStatus::POSTED, $result->status);
        $this->assertSame(2000, $result->requestedAmount);
        $this->assertSame(2000, $result->approvedAmount);
        $this->assertSame($this->person[$wordPressUserId]->id()->toString(), $result->approvedBy);

        $entries = $this->approvalEntries();
        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertTrue($entry->isPosted(), 'Approval posts at once; there is no separate Post step.');
        $this->assertSame($id, $entry->sourceEventId());
        $this->assertSame($entry->id()->toString(), $result->postedJournalEntryId);

        $debits = [];
        $credits = [];
        foreach ($entry->lines() as $line) {
            $account = $this->accounts->findById($line->accountId());
            if ($line->debitCredit()->toString() === 'DEBIT') {
                $debits[] = [$account->name(), $line->amount()];
            } else {
                $credits[] = [$account->name(), $account->type()->toString(), $line->amount()];
            }
        }
        $this->assertSame([['消耗品費', 1200], ['消耗品費', 800]], $debits);
        $this->assertSame([['未払金', 'LIABILITY', 2000]], $credits);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function approvers(): array
    {
        return ['primary manager' => [1], 'accounting manager' => [4], 'proxy manager' => [8]];
    }

    public function test_the_approved_amount_reaches_actual_only_once_approved(): void
    {
        $id = $this->submittedBy(2, [5000]);

        $before = $this->summary->execute(new GetProductionAccountingSummaryQuery($this->production->id()->toString(), 1))->toArray();
        $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 1, 3000));
        $after = $this->summary->execute(new GetProductionAccountingSummaryQuery($this->production->id()->toString(), 1))->toArray();

        $this->assertFalse($before['has_actual'], 'A SUBMITTED Expense is not part of Actual.');
        $this->assertSame(0, $before['total_actual']);
        $this->assertTrue($after['has_actual']);
        $this->assertSame(-3000, $after['total_actual'], 'Actual reflects the approved amount, not the requested 5,000.');
    }

    public function test_approval_reuses_the_organizations_active_unpaid_expenses_account(): void
    {
        $existing = Account::create($this->organization->id(), '未払金', AccountType::fromString(AccountType::LIABILITY));
        $this->accounts->save($existing);
        $id = $this->submittedBy(2);

        $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 1));

        $credit = array_values(array_filter(
            $this->approvalEntries()[0]->lines(),
            static fn ($line): bool => $line->debitCredit()->toString() === 'CREDIT'
        ))[0];
        $this->assertTrue($credit->accountId()->equals($existing->id()));
        $this->assertCount(1, array_filter(
            $this->accounts->findByOrganizationId($this->organization->id()),
            static fn (Account $account): bool => $account->name() === '未払金'
        ));
    }

    public function test_a_single_line_expense_is_approved_for_a_reduced_total_and_both_amounts_are_kept(): void
    {
        $id = $this->submittedBy(2, [5000]);

        $result = $this->approveExpense->execute(new ApproveExpenseCommand($id, 4, 1, 2500));

        $this->assertSame(5000, $result->requestedAmount);
        $this->assertSame(2500, $result->approvedAmount);
        foreach ($this->approvalEntries()[0]->lines() as $line) {
            $this->assertSame(2500, $line->amount());
        }
        $approval = $result->reviews[1];
        $this->assertSame([ExpenseReview::APPROVED, 5000, 2500], [$approval['action'], $approval['requested_amount'], $approval['approved_amount']]);
    }

    /**
     * @dataProvider refusedApprovals
     *
     * @param class-string<\Throwable> $expected
     */
    public function test_refused_approvals_change_nothing(string $case, int $wordPressUserId, ?int $amount, int $revision, string $expected): void
    {
        $id = $case === 'multi-line changed total'
            ? $this->submittedBy(2, [3000, 2000])
            : ($case === 'payer approves' ? $this->submittedBy(3, [5000], $this->person[4]->id()->toString()) : $this->submittedBy(2));

        try {
            $this->approveExpense->execute(new ApproveExpenseCommand($id, $wordPressUserId, $revision, $amount));
            $this->fail("{$case} must be refused.");
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($expected, $exception, $exception->getMessage());
        }

        $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($id));
        $this->assertSame([], $this->approvalEntries());
        $this->assertSame([], array_filter($this->notified(), static fn (string $n): bool => str_ends_with($n, ':expense_approved')));
    }

    /**
     * @return array<string, array{string, int, ?int, int, class-string<\Throwable>}>
     */
    public static function refusedApprovals(): array
    {
        return [
            'zero amount' => ['zero amount', 1, 0, 1, InvalidArgumentException::class],
            'negative amount' => ['negative amount', 1, -1, 1, InvalidArgumentException::class],
            'multi-line changed total' => ['multi-line changed total', 1, 4000, 1, InvalidArgumentException::class],
            'stale revision' => ['stale revision', 1, null, 2, ExpenseStateConflictException::class],
            'payer approves' => ['payer approves', 4, null, 1, ExpenseAccessDeniedException::class],
            'another member' => ['another member', 3, null, 1, ExpenseAccessDeniedException::class],
            'ticket manager' => ['ticket manager', 5, null, 1, ExpenseAccessDeniedException::class],
            'participant manager only' => ['participant manager only', 9, null, 1, ExpenseAccessDeniedException::class],
            'proxy with a deactivated role' => ['proxy with a deactivated role', 10, null, 1, ExpenseAccessDeniedException::class],
            'outsider' => ['outsider', 6, null, 1, ExpenseAccessDeniedException::class],
            'the applicant' => ['the applicant', 2, null, 1, ExpenseAccessDeniedException::class],
        ];
    }

    public function test_an_accounting_manager_cannot_approve_their_own_expense(): void
    {
        $id = $this->submittedBy(4);

        try {
            $this->approveExpense->execute(new ApproveExpenseCommand($id, 4, 1));
            $this->fail('Self-approval must be refused.');
        } catch (ExpenseAccessDeniedException $exception) {
            $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($id));
        }

        $this->approveExpense->execute(new ApproveExpenseCommand($id, 7, 1));
        $this->assertSame(ExpenseStatus::POSTED, $this->status($id));
    }

    /**
     * 自己承認の禁止 applies to every approver: neither the PrimaryManager
     * nor a 代理人 may approve an Expense they submitted, and a 代理人 may
     * not approve one that reimburses them. Nothing is posted or announced
     * as approved; another approver still can.
     */
    public function test_no_approver_can_approve_an_expense_they_submitted_or_are_reimbursed_by(): void
    {
        $cases = [
            'primary manager submits' => [$this->submittedBy(1), 1],
            'proxy manager submits' => [$this->submittedBy(8), 8],
            'proxy manager is reimbursed' => [$this->submittedBy(3, [5000], $this->person[8]->id()->toString()), 8],
        ];

        foreach ($cases as $case => [$id, $approver]) {
            try {
                $this->approveExpense->execute(new ApproveExpenseCommand($id, $approver, 1));
                $this->fail("{$case}: self-approval must be refused.");
            } catch (ExpenseAccessDeniedException $exception) {
                $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($id), $case);
            }
        }

        $this->assertSame([], $this->approvalEntries());
        $this->assertSame([], array_filter($this->notified(), static fn (string $n): bool => str_ends_with($n, ':expense_approved')));

        foreach ($cases as [$id]) {
            $this->approveExpense->execute(new ApproveExpenseCommand($id, 4, 1));
            $this->assertSame(ExpenseStatus::POSTED, $this->status($id));
        }
        $this->assertCount(3, $this->approvalEntries());
    }

    /**
     * Approval rights add nothing else: a 代理人 can approve, but cannot edit
     * someone else's Expense the way the PrimaryManager / 会計管理者 can
     * (`Accounting.Update`).
     */
    public function test_a_proxy_manager_cannot_edit_someone_elses_expense(): void
    {
        $id = $this->draftBy(2);

        try {
            $this->updateExpense->execute(new UpdateExpenseCommand($id, 8, [
                ['account_id' => $this->expenseAccountId, 'amount' => 9000],
            ]));
            $this->fail('A proxy manager must not edit another member\'s Expense.');
        } catch (ExpenseAccessDeniedException $exception) {
            $this->assertSame(5000, $this->expenses->findById(ExpenseId::fromString($id))->totalAmount());
        }
    }

    public function test_approving_content_edited_after_review_is_refused_with_a_conflict(): void
    {
        $id = $this->submittedBy(2, [5000]);
        $this->updateExpense->execute(new UpdateExpenseCommand($id, 2, [
            ['account_id' => $this->expenseAccountId, 'amount' => 9000],
        ]));

        try {
            $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 1));
            $this->fail('Revision 1 was reviewed but revision 2 is current.');
        } catch (ExpenseStateConflictException $exception) {
            $this->assertSame([], $this->approvalEntries());
        }

        $this->assertSame(9000, $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 2))->approvedAmount);
    }

    public function test_approving_twice_never_posts_a_second_journal_entry(): void
    {
        $id = $this->submittedBy(2);
        $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 1));

        foreach ([1, 4] as $wordPressUserId) {
            try {
                $this->approveExpense->execute(new ApproveExpenseCommand($id, $wordPressUserId, 1));
                $this->fail('A second approval must be refused.');
            } catch (ExpenseStateConflictException $exception) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertCount(1, $this->approvalEntries());
    }

    public function test_an_existing_approval_journal_entry_blocks_another_posting(): void
    {
        $id = $this->submittedBy(2);
        $existing = JournalEntry::create(
            $this->organization->id(),
            $this->production->id(),
            new DateTimeImmutable(),
            'Expense Approved (other request)',
            [
                JournalEntryLine::create(AccountId::fromString($this->expenseAccountId), DebitCredit::debit(), 5000),
                JournalEntryLine::create(AccountId::fromString($this->expenseAccountId), DebitCredit::credit(), 5000),
            ],
            $this->person[1]->id(),
            ApproveExpenseUseCase::SOURCE_EVENT_TYPE,
            $id
        );
        $existing->post($this->person[1]->id());
        $this->journalEntries->save($existing);

        try {
            $this->approveExpense->execute(new ApproveExpenseCommand($id, 4, 1));
            $this->fail('The Expense already has its approval Journal Entry.');
        } catch (ExpenseStateConflictException $exception) {
            $this->assertCount(1, $this->approvalEntries());
            $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($id));
        }
    }

    public function test_a_failed_journal_entry_write_rolls_everything_back_and_notifies_nobody(): void
    {
        $id = $this->submittedBy(2);
        $sentBefore = count($this->notifications->sent());
        $this->journalEntries->failNextSave();

        try {
            $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 1));
            $this->fail('The simulated write failure must surface.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated JournalEntry save failure.', $exception->getMessage());
        }

        $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($id));
        $this->assertSame([], $this->approvalEntries());
        $this->assertCount($sentBefore, $this->notifications->sent());
        $this->assertSame(1, $this->transactions->rollbacks());
    }

    public function test_a_failed_expense_write_after_posting_rolls_the_journal_entry_back(): void
    {
        $id = $this->submittedBy(2);
        $sentBefore = count($this->notifications->sent());
        $this->expenses->failNextSave();

        try {
            $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 1));
            $this->fail('The simulated write failure must surface.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated Expense save failure.', $exception->getMessage());
        }

        $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($id));
        $this->assertSame([], $this->approvalEntries(), 'The Journal Entry saved first must be rolled back too.');
        $this->assertCount($sentBefore, $this->notifications->sent());

        $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 1));
        $this->assertCount(1, $this->approvalEntries());
    }

    public function test_the_applicant_is_notified_of_the_approval_after_the_commit(): void
    {
        $id = $this->submittedBy(2, [5000]);

        $this->approveExpense->execute(new ApproveExpenseCommand($id, 4, 1, 4000));

        $last = $this->notifications->sent()[count($this->notifications->sent()) - 1];
        $this->assertSame($this->person[2]->id()->toString(), $last['person_id']);
        $this->assertSame('expense_approved', $last['type']);
        $this->assertSame($id, $last['payload']['target_id']);
        $this->assertStringContainsString('申請額 5,000円 / 承認額 4,000円', $last['payload']['message']);
        $this->assertSame(0, $this->notifications->sentInsideTransaction());
    }

    public function test_approval_is_refused_while_the_organization_has_accounting_disabled(): void
    {
        $this->build(false);
        $id = $this->submittedBy(2);

        try {
            $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 1));
            $this->fail('Approval must be refused while accounting is disabled.');
        } catch (ExpenseAccountingDisabledException $exception) {
            $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($id));
            $this->assertSame([], $this->approvalEntries());
        }

        // Rejecting posts nothing, so it is still possible.
        $this->rejectExpense->execute(new RejectExpenseCommand($id, 1, '会計未開始のため'));
        $this->assertSame(ExpenseStatus::REJECTED, $this->status($id));
    }

    // --- reject / resubmit ------------------------------------------------

    /**
     * @dataProvider approvers
     */
    public function test_an_approver_rejects_with_a_reason_and_the_applicant_is_told_why(int $wordPressUserId): void
    {
        $id = $this->submittedBy(2);

        $result = $this->rejectExpense->execute(new RejectExpenseCommand($id, $wordPressUserId, '領収書を添付してください'));

        $this->assertSame(ExpenseStatus::REJECTED, $result->status);
        $this->assertSame('領収書を添付してください', $result->rejectionReason);
        $this->assertSame([], $this->approvalEntries());

        $last = $this->notifications->sent()[count($this->notifications->sent()) - 1];
        $this->assertSame($this->person[2]->id()->toString(), $last['person_id']);
        $this->assertSame('expense_rejected', $last['type']);
        $this->assertStringContainsString('領収書を添付してください', $last['payload']['message']);
        $this->assertSame(0, $this->notifications->sentInsideTransaction());
    }

    public function test_reject_without_a_reason_is_refused_and_changes_nothing(): void
    {
        $id = $this->submittedBy(2);

        foreach (['', '   '] as $reason) {
            try {
                $this->rejectExpense->execute(new RejectExpenseCommand($id, 1, $reason));
                $this->fail('A blank rejection reason must be refused.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($id));
            }
        }
    }

    /**
     * @dataProvider nonApprovers
     */
    public function test_people_without_accounting_rights_cannot_reject(int $wordPressUserId): void
    {
        $id = $this->submittedBy(2);

        try {
            $this->rejectExpense->execute(new RejectExpenseCommand($id, $wordPressUserId, '不備あり'));
            $this->fail('Rejecting must require accounting rights.');
        } catch (ExpenseAccessDeniedException $exception) {
            $this->assertSame(ExpenseStatus::SUBMITTED, $this->status($id));
        }
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonApprovers(): array
    {
        return [
            'the applicant' => [2],
            'another member' => [3],
            'ticket manager' => [5],
            'outsider' => [6],
            'participant manager only' => [9],
            'proxy with a deactivated role' => [10],
        ];
    }

    public function test_a_draft_or_posted_expense_cannot_be_rejected(): void
    {
        $draft = $this->draftBy(2);
        $posted = $this->submittedBy(2);
        $this->approveExpense->execute(new ApproveExpenseCommand($posted, 1, 1));

        foreach ([$draft, $posted] as $id) {
            try {
                $this->rejectExpense->execute(new RejectExpenseCommand($id, 1, '不備あり'));
                $this->fail('Only a SUBMITTED Expense can be rejected.');
            } catch (ExpenseStateConflictException $exception) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_applicant_corrects_a_rejected_expense_and_resubmits_it(): void
    {
        $id = $this->submittedBy(2, [5000]);
        $this->rejectExpense->execute(new RejectExpenseCommand($id, 4, '金額を確認してください'));

        $this->updateExpense->execute(new UpdateExpenseCommand($id, 2, [
            ['account_id' => $this->expenseAccountId, 'amount' => 4600],
        ]));
        $result = $this->applicantTransitions->resubmit(new ExpenseTransitionCommand($id, 2));

        $this->assertSame(ExpenseStatus::SUBMITTED, $result->status);
        $this->assertSame(2, $result->revision);
        $this->assertSame(4600, $result->requestedAmount);
        $this->assertSame(
            ['1:SUBMITTED:5000', '1:REJECTED:5000', '2:SUBMITTED:4600'],
            array_map(static fn (array $review): string => "{$review['revision']}:{$review['action']}:{$review['requested_amount']}", $result->reviews)
        );

        $resubmitNotices = array_filter($this->notified(), static fn (string $n): bool => str_ends_with($n, ':expense_submitted'));
        $this->assertCount(8, $resubmitNotices, 'Approvers 1, 4, 7 and 8 are told about the submission and again about the resubmission.');

        $this->assertSame(4600, $this->approveExpense->execute(new ApproveExpenseCommand($id, 1, 2))->approvedAmount);
    }

    public function test_the_capability_directory_lists_exactly_the_people_canForProduction_accepts(): void
    {
        $orgAuth = new OrganizationAuthorizationService($this->people, $this->memberships);
        $prodAuth = new ProductionAuthorizationService($orgAuth, $this->delegates, $this->participants);
        $directory = new CoreProductionCapabilityDirectoryAdapter($prodAuth, $this->productions, $this->delegates, $this->people);
        $authorization = new CoreAuthorizationAdapter($prodAuth, $this->productions, $this->people);

        foreach ([AccountingCapability::APPROVE_EXPENSE => [1, 4, 7, 8], AccountingCapability::MANAGE => [1, 4, 7]] as $capability => $expected) {
            $listed = array_map(
                static fn (PersonId $id): string => $id->toString(),
                $directory->personIdsWithProductionCapability($this->production->id(), $capability)
            );

            foreach ($this->person as $wordPressUserId => $person) {
                $this->assertSame(
                    $authorization->canForProduction($person->id(), $this->production->id(), $capability),
                    in_array($person->id()->toString(), $listed, true),
                    "{$capability}: user {$wordPressUserId}"
                );
            }

            $expectedIds = array_map(fn (int $user): string => $this->person[$user]->id()->toString(), $expected);
            sort($expectedIds);
            sort($listed);
            $this->assertSame($expectedIds, $listed, $capability);
        }
    }
}
