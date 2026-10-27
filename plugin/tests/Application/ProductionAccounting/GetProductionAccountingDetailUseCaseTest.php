<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ProductionAccounting;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\ProductionAccounting\GetProductionAccountingDetailUseCase;
use StageArt\Application\ProductionAccounting\GetProductionAccountingSummaryQuery;
use StageArt\Application\ProductionAccounting\ProductionAccountingAccessDeniedException;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Budget\Budget;
use StageArt\Domain\Budget\BudgetLine;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryBudgetRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

/**
 * 公演会計の費目別の予算・実績と取引明細 (OrganizationAccountingAndProductionCostPolicy.md §3).
 */
final class GetProductionAccountingDetailUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProjectRepository $projects;
    private InMemoryProductionRepository $productions;
    private InMemoryAccountRepository $accounts;
    private InMemoryBudgetRepository $budgets;
    private InMemoryJournalEntryRepository $journalEntries;
    private InMemoryProductionDelegateRepository $delegates;
    private GetProductionAccountingDetailUseCase $getDetail;

    private Organization $organization;
    private Production $production;
    private Person $manager;
    private Account $ticketRevenue;
    private Account $venue;
    private Account $consumables;
    private Account $cash;
    private Account $payable;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->budgets = new InMemoryBudgetRepository();
        $this->journalEntries = new InMemoryJournalEntryRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();

        $prodAuth = new ProductionAuthorizationService(
            new OrganizationAuthorizationService($this->people, $this->memberships),
            $this->delegates,
            new InMemoryParticipantRepository()
        );

        $this->getDetail = new GetProductionAccountingDetailUseCase(
            $this->budgets,
            $this->journalEntries,
            $this->accounts,
            new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver($this->projects)),
            new CoreAuthorizationAdapter($prodAuth, $this->productions, $this->people),
            new CoreIdentityAdapter($this->people)
        );

        $this->organization = Organization::create(new OrganizationName('Theatre Co'));

        $this->manager = Person::create(1);
        $this->people->save($this->manager);
        $this->memberships->save(Membership::createOwnerMembership($this->organization->id(), $this->manager->id()));

        $member = Person::create(2);
        $this->people->save($member);
        $this->memberships->save(Membership::create($this->organization->id(), $member->id(), RoleKey::member()));

        $project = Project::create($this->organization->id(), 'Spring Season');
        $this->projects->save($project);

        $this->production = Production::create($project->id(), new ProductionName('Autumn Play'), $this->manager->id());
        $this->productions->save($this->production);

        $this->ticketRevenue = $this->addAccount('チケット売上', AccountType::REVENUE);
        $this->venue = $this->addAccount('会場費', AccountType::EXPENSE);
        $this->consumables = $this->addAccount('消耗品費', AccountType::EXPENSE);
        $this->cash = $this->addAccount('現金', AccountType::ASSET);
        $this->payable = $this->addAccount('未払金', AccountType::LIABILITY);
    }

    public function test_lists_budget_and_actual_per_category_and_the_transactions(): void
    {
        $this->givenActiveBudget([[$this->ticketRevenue, 300000], [$this->venue, 120000]]);
        $this->givenPostedEntry('2026-11-02', 'ExpenseApproved', [
            [$this->consumables, DebitCredit::debit(), 3000],
            [$this->payable, DebitCredit::credit(), 3000],
        ]);
        $this->givenPostedEntry('2026-11-01', 'チケット売上', [
            [$this->cash, DebitCredit::debit(), 50000],
            [$this->ticketRevenue, DebitCredit::credit(), 50000],
        ]);

        $result = $this->getDetail->execute($this->query(1))->toArray();

        $this->assertTrue($result['has_budget']);
        $this->assertSame(
            [
                ['チケット売上', 'REVENUE', 300000, 50000],
                ['会場費', 'EXPENSE', 120000, 0],
                ['消耗品費', 'EXPENSE', 0, 3000],
            ],
            array_map(static fn (array $c): array => [$c['name'], $c['type'], $c['budget'], $c['actual']], $result['categories'])
        );

        // 現金 / 未払金 are not 費目 - only the REVENUE / EXPENSE side of each entry is listed, oldest first.
        $this->assertSame(
            [
                ['2026-11-01', 'チケット売上', 'チケット売上', 50000, null],
                ['2026-11-02', 'ExpenseApproved', '消耗品費', null, 3000],
            ],
            array_map(
                static fn (array $t): array => [$t['journal_date'], $t['description'], $t['account_name'], $t['income'], $t['expense']],
                $result['transactions']
            )
        );
    }

    public function test_budget_is_null_without_an_active_budget_and_a_draft_budget_is_ignored(): void
    {
        $this->budgets->save(Budget::create($this->production->id(), 'Draft', [BudgetLine::create($this->venue->id(), 90000)], $this->manager->id()));
        $this->givenPostedEntry('2026-11-03', '会場費', [
            [$this->venue, DebitCredit::debit(), 80000],
            [$this->payable, DebitCredit::credit(), 80000],
        ]);

        $result = $this->getDetail->execute($this->query(1))->toArray();

        $this->assertFalse($result['has_budget']);
        $this->assertSame([['会場費', null, 80000]], array_map(static fn (array $c): array => [$c['name'], $c['budget'], $c['actual']], $result['categories']));
    }

    public function test_draft_journal_entries_are_not_actuals(): void
    {
        $entry = JournalEntry::create(
            $this->organization->id(),
            $this->production->id(),
            new DateTimeImmutable('2026-11-04'),
            '未転記',
            [JournalEntryLine::create($this->venue->id(), DebitCredit::debit(), 1000), JournalEntryLine::create($this->cash->id(), DebitCredit::credit(), 1000)],
            $this->manager->id()
        );
        $this->journalEntries->save($entry);

        $result = $this->getDetail->execute($this->query(1))->toArray();

        $this->assertSame([], $result['categories']);
        $this->assertSame([], $result['transactions']);
    }

    public function test_an_accounting_manager_delegate_can_view_the_detail(): void
    {
        $delegate = Person::create(50);
        $this->people->save($delegate);
        $this->delegates->save(ProductionDelegate::create($this->production->id(), $delegate->id(), RoleKey::accountingManager(), $this->manager->id()));

        $this->assertSame($this->production->id()->toString(), $this->getDetail->execute($this->query(50))->productionId);
    }

    public function test_a_plain_member_cannot_view_the_detail(): void
    {
        $this->expectException(ProductionAccountingAccessDeniedException::class);
        $this->getDetail->execute($this->query(2));
    }

    public function test_a_delegate_without_accounting_manager_cannot_view_the_detail(): void
    {
        $delegate = Person::create(51);
        $this->people->save($delegate);
        $this->delegates->save(ProductionDelegate::create($this->production->id(), $delegate->id(), RoleKey::checkInManager(), $this->manager->id()));

        $this->expectException(ProductionAccountingAccessDeniedException::class);
        $this->getDetail->execute($this->query(51));
    }

    public function test_an_unknown_production_is_not_found(): void
    {
        $this->expectException(ProductionNotFoundException::class);
        $this->getDetail->execute(new GetProductionAccountingSummaryQuery(ProductionId::generate()->toString(), 1));
    }

    private function query(int $wordPressUserId): GetProductionAccountingSummaryQuery
    {
        return new GetProductionAccountingSummaryQuery($this->production->id()->toString(), $wordPressUserId);
    }

    private function addAccount(string $name, string $type): Account
    {
        $account = Account::create($this->organization->id(), $name, AccountType::fromString($type));
        $this->accounts->save($account);

        return $account;
    }

    /**
     * @param array<int, array{0: Account, 1: int}> $lines
     */
    private function givenActiveBudget(array $lines): void
    {
        $budget = Budget::create(
            $this->production->id(),
            'Plan',
            array_map(static fn (array $l): BudgetLine => BudgetLine::create($l[0]->id(), $l[1]), $lines),
            $this->manager->id()
        );
        $budget->activate($this->manager->id());
        $this->budgets->save($budget);
    }

    /**
     * @param array<int, array{0: Account, 1: DebitCredit, 2: int}> $lines
     */
    private function givenPostedEntry(string $date, string $description, array $lines): void
    {
        $entry = JournalEntry::create(
            $this->organization->id(),
            $this->production->id(),
            new DateTimeImmutable($date),
            $description,
            array_map(static fn (array $l): JournalEntryLine => JournalEntryLine::create($l[0]->id(), $l[1], $l[2]), $lines),
            $this->manager->id()
        );
        $entry->post($this->manager->id());
        $this->journalEntries->save($entry);
    }
}
