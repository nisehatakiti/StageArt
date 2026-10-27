<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\OrganizationAccounting;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\OrganizationAccounting\ListOrganizationProductionResultsQuery;
use StageArt\Application\OrganizationAccounting\ListOrganizationProductionResultsUseCase;
use StageArt\Application\OrganizationAccounting\OrganizationAccountingAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

/**
 * 団体会計の公演別実績 (OrganizationAccountingAndProductionCostPolicy.md §2).
 * WordPress users: 1 Owner, 3 plain member.
 */
final class ListOrganizationProductionResultsUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationRepository $organizations;
    private InMemoryProjectRepository $projects;
    private InMemoryProductionRepository $productions;
    private InMemoryAccountRepository $accounts;
    private InMemoryJournalEntryRepository $journalEntries;
    private ListOrganizationProductionResultsUseCase $listResults;

    private Organization $organization;
    private Project $project;
    private Person $owner;
    private Account $ticketRevenue;
    private Account $venue;
    private Account $consumables;
    private Account $cash;
    private Account $payable;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->journalEntries = new InMemoryJournalEntryRepository();

        $prodAuth = new ProductionAuthorizationService(
            new OrganizationAuthorizationService($this->people, $this->memberships),
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );

        $this->listResults = new ListOrganizationProductionResultsUseCase(
            $this->journalEntries,
            $this->accounts,
            new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver($this->projects)),
            new CoreOrganizationContextAdapter($this->organizations),
            new CoreAuthorizationAdapter($prodAuth, $this->productions, $this->people),
            new CoreIdentityAdapter($this->people)
        );

        $this->organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($this->organization);
        $this->project = Project::create($this->organization->id(), 'Season');
        $this->projects->save($this->project);

        $this->owner = Person::create(1);
        $this->people->save($this->owner);
        $this->memberships->save(Membership::createOwnerMembership($this->organization->id(), $this->owner->id()));

        $member = Person::create(3);
        $this->people->save($member);
        $this->memberships->save(Membership::create($this->organization->id(), $member->id(), RoleKey::member()));

        $this->ticketRevenue = $this->addAccount('チケット売上', AccountType::REVENUE);
        $this->venue = $this->addAccount('会場費', AccountType::EXPENSE);
        $this->consumables = $this->addAccount('消耗品費', AccountType::EXPENSE);
        $this->cash = $this->addAccount('現金', AccountType::ASSET);
        $this->payable = $this->addAccount('未払金', AccountType::LIABILITY);
    }

    public function test_lists_revenue_cost_and_profit_per_production_with_totals(): void
    {
        $spring = $this->addProduction('春公演');
        $autumn = $this->addProduction('秋公演');
        $empty = $this->addProduction('冬公演');

        $this->post($spring, [[$this->cash, DebitCredit::debit(), 200000], [$this->ticketRevenue, DebitCredit::credit(), 200000]]);
        $this->post($spring, [[$this->venue, DebitCredit::debit(), 120000], [$this->payable, DebitCredit::credit(), 120000]]);
        $this->post($spring, [[$this->consumables, DebitCredit::debit(), 5000], [$this->payable, DebitCredit::credit(), 5000]]);
        $this->post($autumn, [[$this->venue, DebitCredit::debit(), 80000], [$this->cash, DebitCredit::credit(), 80000]]);
        // Paying 未払金 off moves no 売上 / 原価.
        $this->post($spring, [[$this->payable, DebitCredit::debit(), 120000], [$this->cash, DebitCredit::credit(), 120000]]);

        $result = $this->listResults->execute($this->query(1))->toArray();

        $this->assertSame(
            [
                ['春公演', 200000, 125000, 75000],
                ['秋公演', 0, 80000, -80000],
                ['冬公演', 0, 0, 0],
            ],
            array_map(static fn (array $p): array => [$p['name'], $p['revenue'], $p['cost'], $p['profit']], $result['productions'])
        );
        $this->assertSame(['revenue' => 200000, 'cost' => 205000, 'profit' => -5000], $result['totals']);
        $this->assertSame([$spring->id()->toString(), $autumn->id()->toString(), $empty->id()->toString()], array_column($result['productions'], 'production_id'));
    }

    public function test_the_same_entry_is_counted_once_and_draft_entries_and_other_organizations_are_left_out(): void
    {
        $production = $this->addProduction('春公演');
        $this->post($production, [[$this->venue, DebitCredit::debit(), 10000], [$this->cash, DebitCredit::credit(), 10000]]);

        $draft = JournalEntry::create(
            $this->organization->id(),
            $production->id(),
            new DateTimeImmutable('2026-11-05'),
            '未転記',
            [JournalEntryLine::create($this->venue->id(), DebitCredit::debit(), 999), JournalEntryLine::create($this->cash->id(), DebitCredit::credit(), 999)],
            $this->owner->id()
        );
        $this->journalEntries->save($draft);

        $elsewhere = JournalEntry::create(
            OrganizationId::generate(),
            $production->id(),
            new DateTimeImmutable('2026-11-05'),
            '他団体',
            [JournalEntryLine::create($this->venue->id(), DebitCredit::debit(), 777), JournalEntryLine::create($this->cash->id(), DebitCredit::credit(), 777)],
            $this->owner->id()
        );
        $elsewhere->post($this->owner->id());
        $this->journalEntries->save($elsewhere);

        $result = $this->listResults->execute($this->query(1))->toArray();

        $this->assertSame(10000, $result['productions'][0]['cost']);
        $this->assertSame(10000, $result['totals']['cost']);
    }

    public function test_closing_is_confirmed_once_the_production_is_completed(): void
    {
        $open = $this->addProduction('進行中');
        $closed = $this->addProduction('決算済み');
        $closed->changeSlug(new ProductionSlug('closed-production'));
        $closed->activate();
        $closed->complete();
        $this->productions->save($closed);

        $result = $this->listResults->execute($this->query(1))->toArray();

        $this->assertSame(
            [[$open->id()->toString(), false], [$closed->id()->toString(), true]],
            array_map(static fn (array $p): array => [$p['production_id'], $p['closing_confirmed']], $result['productions'])
        );
    }

    public function test_a_plain_member_cannot_view_the_results(): void
    {
        $this->expectException(OrganizationAccountingAccessDeniedException::class);
        $this->listResults->execute($this->query(3));
    }

    public function test_an_unknown_organization_is_not_found(): void
    {
        $this->expectException(OrganizationNotFoundException::class);
        $this->listResults->execute(new ListOrganizationProductionResultsQuery(OrganizationId::generate()->toString(), 1));
    }

    private function query(int $wordPressUserId): ListOrganizationProductionResultsQuery
    {
        return new ListOrganizationProductionResultsQuery($this->organization->id()->toString(), $wordPressUserId);
    }

    private function addAccount(string $name, string $type): Account
    {
        $account = Account::create($this->organization->id(), $name, AccountType::fromString($type));
        $this->accounts->save($account);

        return $account;
    }

    private function addProduction(string $name): Production
    {
        $production = Production::create($this->project->id(), new ProductionName($name), $this->owner->id());
        $this->productions->save($production);

        return $production;
    }

    /**
     * @param array<int, array{0: Account, 1: DebitCredit, 2: int}> $lines
     */
    private function post(Production $production, array $lines): void
    {
        $entry = JournalEntry::create(
            $this->organization->id(),
            $production->id(),
            new DateTimeImmutable('2026-11-01'),
            '取引',
            array_map(static fn (array $l): JournalEntryLine => JournalEntryLine::create($l[0]->id(), $l[1], $l[2]), $lines),
            $this->owner->id()
        );
        $entry->post($this->owner->id());
        $this->journalEntries->save($entry);
    }
}
