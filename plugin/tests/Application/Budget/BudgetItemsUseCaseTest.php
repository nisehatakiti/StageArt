<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Budget;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Accounting\PresetAccountCatalog;
use StageArt\Application\Accounting\PresetAccountProvisioner;
use StageArt\Application\Budget\ActivateBudgetCommand;
use StageArt\Application\Budget\ActivateBudgetUseCase;
use StageArt\Application\Budget\BudgetAccessDeniedException;
use StageArt\Application\Budget\BudgetItemCatalog;
use StageArt\Application\Budget\BudgetItemLineFactory;
use StageArt\Application\Budget\BudgetLineFactory;
use StageArt\Application\Budget\BudgetResult;
use StageArt\Application\Budget\CreateBudgetCommand;
use StageArt\Application\Budget\CreateBudgetUseCase;
use StageArt\Application\Budget\GetBudgetQuery;
use StageArt\Application\Budget\GetBudgetUseCase;
use StageArt\Application\Budget\ListBudgetItemsUseCase;
use StageArt\Application\Budget\ListProductionBudgetsQuery;
use StageArt\Application\Budget\UpdateBudgetCommand;
use StageArt\Application\Budget\UpdateBudgetUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryBudgetRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * 予算策定 (第1スプリント): Budgets saved from 予算項目 instead of Account
 * ids, with the preset Account set registered on the first save.
 */
final class BudgetItemsUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProjectRepository $projects;
    private InMemoryProductionRepository $productions;
    private InMemoryAccountRepository $accounts;
    private InMemoryBudgetRepository $budgets;

    private ListBudgetItemsUseCase $listItems;
    private CreateBudgetUseCase $createBudget;
    private UpdateBudgetUseCase $updateBudget;
    private GetBudgetUseCase $getBudget;
    private ActivateBudgetUseCase $activateBudget;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->budgets = new InMemoryBudgetRepository();

        $prodAuth = new ProductionAuthorizationService(
            new OrganizationAuthorizationService($this->people, $this->memberships),
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver($this->projects));
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($prodAuth, $this->productions, $this->people);
        $transactions = new InMemoryTransactionManager();
        $lineFactory = new BudgetLineFactory($this->accounts);
        $presets = new PresetAccountProvisioner($this->accounts);
        $itemLineFactory = new BudgetItemLineFactory($this->accounts, $presets);

        $this->listItems = new ListBudgetItemsUseCase(new BudgetItemCatalog($this->accounts, $presets), $productionContext, $identity, $authorization);
        $this->createBudget = new CreateBudgetUseCase($this->budgets, $productionContext, $identity, $authorization, $lineFactory, $transactions, $itemLineFactory);
        $this->updateBudget = new UpdateBudgetUseCase($this->budgets, $productionContext, $identity, $authorization, $lineFactory, $transactions, $itemLineFactory);
        $this->getBudget = new GetBudgetUseCase($this->budgets, $identity, $authorization);
        $this->activateBudget = new ActivateBudgetUseCase($this->budgets, $identity, $authorization, $transactions);
    }

    /**
     * @return array{0: Organization, 1: Production} WordPress user 1 is the PrimaryManager, 2 a plain Member
     */
    private function givenProduction(): array
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

        return [$organization, $production];
    }

    private function addAccount(Organization $organization, string $name, string $type, bool $active = true): Account
    {
        $account = Account::create($organization->id(), $name, AccountType::fromString($type));

        if (! $active) {
            $account->deactivate();
        }

        $this->accounts->save($account);

        return $account;
    }

    /**
     * @return array<string, mixed> item arrays keyed by item key
     */
    private function itemsByKey(Production $production, int $wpUserId = 1): array
    {
        $items = [];

        foreach ($this->listItems->execute(new ListProductionBudgetsQuery($production->id()->toString(), $wpUserId)) as $item) {
            $items[$item->key] = $item->toArray();
        }

        return $items;
    }

    /**
     * @return array<string, int> amount per Account name
     */
    private function amountsByAccountName(BudgetResult $budget): array
    {
        $amounts = [];

        foreach ($budget->lines as $line) {
            $account = $this->accounts->findById(AccountId::fromString($line->accountId));

            $this->assertNotNull($account);
            $amounts[$account->name()] = $line->amount;
        }

        return $amounts;
    }

    private function accountCount(Organization $organization): int
    {
        return count($this->accounts->findByOrganizationId($organization->id()));
    }

    public function test_items_list_presets_in_plain_language_without_registering_anything(): void
    {
        [$organization, $production] = $this->givenProduction();

        $items = $this->itemsByKey($production);

        $this->assertSame(
            ['ticket_revenue', 'merchandise_revenue', 'other_revenue', 'venue', 'equipment_rental', 'outsourcing', 'advertising', 'communication', 'travel', 'consumables', 'miscellaneous'],
            array_keys($items)
        );
        $this->assertSame('チケット収入', $items['ticket_revenue']['label']);
        $this->assertSame('REVENUE', $items['ticket_revenue']['type']);
        $this->assertSame('外注費（スタッフ・キャスト）', $items['outsourcing']['label']);
        $this->assertSame('EXPENSE', $items['travel']['type']);
        $this->assertNull($items['venue']['account_id']);
        $this->assertTrue($items['venue']['available']);
        $this->assertSame(0, $this->accountCount($organization));
    }

    public function test_first_save_registers_the_preset_accounts_and_skips_zero_amounts(): void
    {
        [$organization, $production] = $this->givenProduction();

        $budget = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), '河童ホームラン2026予算 Version1', [], [
            ['key' => 'ticket_revenue', 'amount' => 450000],
            ['key' => 'venue', 'amount' => '200000', 'notes' => '劇場見積書より'],
            ['key' => 'travel', 'amount' => 0],
        ]));

        $this->assertSame('DRAFT', $budget->status);
        $this->assertSame(['チケット売上' => 450000, '会場費' => 200000], $this->amountsByAccountName($budget));
        $this->assertSame('劇場見積書より', $budget->lines[1]->notes);

        $names = array_map(static fn (Account $account): string => $account->name(), $this->accounts->findByOrganizationId($organization->id()));
        sort($names);
        $expected = array_map(static fn ($preset): string => $preset->name, PresetAccountCatalog::all());
        sort($expected);
        $this->assertSame($expected, $names);

        $items = $this->itemsByKey($production);
        $this->assertNotNull($items['travel']['account_id']);
    }

    public function test_existing_accounts_are_reused_not_duplicated_or_renamed(): void
    {
        [$organization, $production] = $this->givenProduction();
        $venue = $this->addAccount($organization, '会場費', AccountType::EXPENSE);
        $this->addAccount($organization, '現金', AccountType::ASSET);
        $this->addAccount($organization, '元入金', AccountType::EQUITY);

        $budget = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [], [
            ['key' => 'venue', 'amount' => 300000],
        ]));

        $this->assertSame($venue->id()->toString(), $budget->lines[0]->accountId);
        $this->assertSame(count(PresetAccountCatalog::all()) + 1, $this->accountCount($organization));

        $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'B会場案', [], [
            ['key' => 'venue', 'amount' => 250000],
        ]));
        $this->assertSame(count(PresetAccountCatalog::all()) + 1, $this->accountCount($organization));
    }

    public function test_legacy_vehicle_travel_account_is_offered_as_its_own_item_and_kept(): void
    {
        [$organization, $production] = $this->givenProduction();
        $legacy = $this->addAccount($organization, '車両交通費', AccountType::EXPENSE);
        $this->addAccount($organization, '現金', AccountType::ASSET);

        $items = $this->itemsByKey($production);
        $key = 'account:' . $legacy->id()->toString();

        $this->assertArrayHasKey($key, $items);
        $this->assertSame('車両交通費', $items[$key]['label']);
        $this->assertFalse($items[$key]['preset']);
        $this->assertSame($key, array_keys($items)[count($items) - 1]);
        $this->assertArrayNotHasKey('account:' . $this->accounts->findByOrganizationId($organization->id())[1]->id()->toString(), $items);

        $budget = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), '旧科目あり', [], [
            ['key' => $key, 'amount' => 12000],
        ]));

        $this->assertSame($legacy->id()->toString(), $budget->lines[0]->accountId);
        $this->assertSame('車両交通費', $legacy->name());
    }

    public function test_a_same_name_account_of_another_type_blocks_the_preset_instead_of_duplicating_it(): void
    {
        [$organization, $production] = $this->givenProduction();
        $this->addAccount($organization, '会場費', AccountType::REVENUE);

        $items = $this->itemsByKey($production);
        $this->assertFalse($items['venue']['available']);

        try {
            $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [], [
                ['key' => 'venue', 'amount' => 1000],
            ]));
            $this->fail('A blocked preset must not take an amount.');
        } catch (InvalidArgumentException $exception) {
            $venues = array_filter(
                $this->accounts->findByOrganizationId($organization->id()),
                static fn (Account $account): bool => $account->name() === '会場費'
            );
            $this->assertCount(1, $venues);
        }
    }

    public function test_an_inactive_preset_account_is_not_reactivated_or_recreated(): void
    {
        [$organization, $production] = $this->givenProduction();
        $this->addAccount($organization, '通信費', AccountType::EXPENSE, false);

        $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [], [
            ['key' => 'venue', 'amount' => 1000],
        ]));

        $communication = array_values(array_filter(
            $this->accounts->findByOrganizationId($organization->id()),
            static fn (Account $account): bool => $account->name() === '通信費'
        ));
        $this->assertCount(1, $communication);
        $this->assertFalse($communication[0]->isActive());
        $this->assertFalse($this->itemsByKey($production)['communication']['available']);
    }

    public function test_saved_amounts_can_be_edited_and_read_back(): void
    {
        [$organization, $production] = $this->givenProduction();

        $created = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'Version1', [], [
            ['key' => 'ticket_revenue', 'amount' => 450000],
            ['key' => 'venue', 'amount' => 200000],
        ]));
        $accountsAfterCreate = $this->accountCount($organization);

        $this->updateBudget->execute(new UpdateBudgetCommand($created->id, 1, 'Version2', [], [
            ['key' => 'ticket_revenue', 'amount' => 500000],
            ['key' => 'venue', 'amount' => 0],
            ['key' => 'advertising', 'amount' => 30000],
        ]));

        $reloaded = $this->getBudget->execute(new GetBudgetQuery($created->id, 1));

        $this->assertSame('Version2', $reloaded->name);
        $this->assertSame(['チケット売上' => 500000, '広告宣伝費' => 30000], $this->amountsByAccountName($reloaded));
        $this->assertSame($accountsAfterCreate, $this->accountCount($organization));
    }

    /**
     * @return array<string, array{0: array<int, mixed>}>
     */
    public function invalidItemsProvider(): array
    {
        return [
            'negative amount' => [[['key' => 'venue', 'amount' => -1]]],
            'decimal amount' => [[['key' => 'venue', 'amount' => '1.5']]],
            'amount with comma' => [[['key' => 'venue', 'amount' => '1,000']]],
            'too large amount' => [[['key' => 'venue', 'amount' => 1000000000000]]],
            'missing amount' => [[['key' => 'venue']]],
            'unknown key' => [[['key' => 'no_such_item', 'amount' => 1000]]],
            'balance-sheet preset is not a Budget item' => [[['key' => 'cash', 'amount' => 1000]]],
            'duplicate key' => [[['key' => 'venue', 'amount' => 1000], ['key' => 'venue', 'amount' => 2000]]],
            'every amount is zero' => [[['key' => 'venue', 'amount' => 0]]],
            'no items' => [[]],
            'account of another organization' => [[['key' => 'account:00000000-0000-4000-8000-000000000000', 'amount' => 1000]]],
        ];
    }

    /**
     * @dataProvider invalidItemsProvider
     * @param array<int, mixed> $items
     */
    public function test_invalid_items_are_refused(array $items): void
    {
        [$organization, $production] = $this->givenProduction();

        try {
            $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [], $items));
            $this->fail('Invalid Budget items must be refused.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame([], $this->budgets->findByProductionId($production->id()));
        }
    }

    public function test_validation_failures_register_no_accounts(): void
    {
        [$organization, $production] = $this->givenProduction();

        foreach ([[['key' => 'venue', 'amount' => -1]], [['key' => 'venue', 'amount' => 0]]] as $items) {
            try {
                $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [], $items));
            } catch (InvalidArgumentException $exception) {
            }
        }

        try {
            $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), '  ', [], [['key' => 'venue', 'amount' => 1000]]));
            $this->fail('An empty Budget name must be refused.');
        } catch (InvalidArgumentException $exception) {
        }

        $this->assertSame(0, $this->accountCount($organization));
    }

    public function test_an_active_budget_cannot_be_edited_through_items(): void
    {
        [$organization, $production] = $this->givenProduction();
        $budget = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'Version1', [], [
            ['key' => 'venue', 'amount' => 1000],
        ]));
        $this->activateBudget->execute(new ActivateBudgetCommand($budget->id, 1));

        $this->expectException(InvalidArgumentException::class);
        $this->updateBudget->execute(new UpdateBudgetCommand($budget->id, 1, 'Version1', [], [
            ['key' => 'venue', 'amount' => 2000],
        ]));
    }

    public function test_a_member_without_accounting_rights_can_neither_list_nor_save_items(): void
    {
        [$organization, $production] = $this->givenProduction();

        try {
            $this->itemsByKey($production, 2);
            $this->fail('A plain Member must not list Budget items.');
        } catch (BudgetAccessDeniedException $exception) {
        }

        try {
            $this->createBudget->execute(new CreateBudgetCommand(2, $production->id()->toString(), 'A会場案', [], [
                ['key' => 'venue', 'amount' => 1000],
            ]));
            $this->fail('A plain Member must not save a Budget.');
        } catch (BudgetAccessDeniedException $exception) {
        }

        $this->assertSame(0, $this->accountCount($organization));
    }

    public function test_lines_input_is_unchanged(): void
    {
        [$organization, $production] = $this->givenProduction();
        $venue = $this->addAccount($organization, '会場費', AccountType::EXPENSE);

        $budget = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [
            ['account_id' => $venue->id()->toString(), 'amount' => 200000],
        ]));

        $this->assertSame($venue->id()->toString(), $budget->lines[0]->accountId);
        $this->assertSame(1, $this->accountCount($organization));
    }
}
