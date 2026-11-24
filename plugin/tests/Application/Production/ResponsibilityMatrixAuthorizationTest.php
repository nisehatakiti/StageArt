<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Settlement\SettlementCapability;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;

/**
 * PR #8 統合 (2026-11-24): the confirmed 担当区分 table, decision by
 * decision, for every 担当 - and the same answers whatever order the
 * ProductionDelegate rows come back in (the WordPress repository's
 * findByProductionId() has no ORDER BY), only from ACTIVE Roles, and only
 * for the Production the Roles belong to.
 */
final class ResponsibilityMatrixAuthorizationTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryProductionDelegateRepository $delegates;
    private Production $production;
    private Production $otherProduction;

    /** @var array<string, Person> */
    private array $person = [];

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $project = Project::create($organization->id(), 'Season');
        $this->person['pm'] = $this->givenPerson(1);
        $this->production = Production::create($project->id(), new ProductionName('Show'), $this->person['pm']->id());
        $this->otherProduction = Production::create($project->id(), new ProductionName('Other'), $this->givenPerson(99)->id());

        // Rows saved in an order that puts the 公演代理人's two Roles apart
        // and mixes in other people's rows.
        $this->givenRoles('proxy', 2, [RoleKey::rehearsalManager()]);
        $this->givenRoles('accounting', 3, [RoleKey::accountingManager()]);
        $this->givenRoles('rehearsal', 4, [RoleKey::rehearsalManager()]);
        $this->givenRoles('proxy', 2, [RoleKey::participantManager()]);
        $this->givenRoles('participant', 5, [RoleKey::participantManager()]);
        $this->givenRoles('checkin', 6, [RoleKey::checkInManager()]);
        $this->givenRoles('proxyAccounting', 7, [RoleKey::accountingManager(), RoleKey::participantManager(), RoleKey::rehearsalManager()]);
        $this->givenRoles('formerProxy', 8, [RoleKey::participantManager(), RoleKey::rehearsalManager()]);
        $this->deactivate('formerProxy', RoleKey::rehearsalManager());
        // A 公演代理人 of the OTHER Production only.
        $this->person['otherProxy'] = $this->givenPerson(9);
        foreach ([RoleKey::participantManager(), RoleKey::rehearsalManager()] as $role) {
            $this->delegates->save(ProductionDelegate::create($this->otherProduction->id(), $this->person['otherProxy']->id(), $role, $this->otherProduction->primaryManagerPersonId()));
        }
    }

    /**
     * The confirmed table: operations, accounting, Role assignment and the
     * PrimaryManager-only operations.
     *
     * @return array<string, array{string, array<string, bool>}>
     */
    public static function expectations(): array
    {
        $none = [
            'operations' => false, 'joinKeys' => false, 'participants' => false, 'rehearsals' => false,
            'accounting' => false, 'activateBudget' => false, 'approveExpense' => false, 'settlement' => false,
            'assignProxy' => false, 'assignAccounting' => false, 'assignCheckIn' => false, 'assignTicket' => false,
            'complete' => false, 'lifecycle' => false,
        ];
        $all = array_map(static fn (): bool => true, $none);
        $proxy = array_merge($all, ['assignTicket' => false, 'complete' => false, 'lifecycle' => false]);
        $accounting = array_merge($none, ['accounting' => true, 'activateBudget' => true, 'approveExpense' => true, 'settlement' => true]);

        return [
            'PrimaryManager' => ['pm', $all],
            '公演代理人' => ['proxy', $proxy],
            '公演代理人＋会計担当' => ['proxyAccounting', $proxy],
            '会計担当' => ['accounting', $accounting],
            '稽古担当' => ['rehearsal', array_merge($none, ['rehearsals' => true])],
            '参加者担当のみ' => ['participant', array_merge($none, ['participants' => true])],
            '受付担当' => ['checkin', $none],
            '元公演代理人（REHEARSAL_MANAGERがINACTIVE）' => ['formerProxy', array_merge($none, ['participants' => true])],
            '他の公演の公演代理人' => ['otherProxy', $none],
        ];
    }

    /**
     * @dataProvider expectations
     *
     * @param array<string, bool> $expected
     */
    public function test_each_responsibility_gets_exactly_the_confirmed_rights(string $who, array $expected): void
    {
        $this->assertSame($expected, $this->decisions($this->authorization($this->delegates), $this->person[$who]));
    }

    /**
     * @dataProvider expectations
     */
    public function test_the_decisions_do_not_depend_on_the_row_order(string $who): void
    {
        $asStored = $this->decisions($this->authorization($this->delegates), $this->person[$who]);
        $reversed = $this->decisions($this->authorization(new ReversedProductionDelegateRepository($this->delegates)), $this->person[$who]);

        $this->assertSame($asStored, $reversed);
    }

    /**
     * @return array<string, bool>
     */
    private function decisions(ProductionAuthorizationService $authorization, Person $person): array
    {
        $production = $this->production;
        $can = static fn (string $capability): bool => $authorization->hasProductionCapability($person, $production, $capability);

        return [
            'operations' => $authorization->canActAsProductionManager($person, $production),
            'joinKeys' => $authorization->canManageJoinKeys($person, $production),
            'participants' => $authorization->canManageParticipants($person, $production),
            'rehearsals' => $can(RehearsalCapability::MANAGE),
            'accounting' => $can(AccountingCapability::MANAGE),
            'activateBudget' => $can(AccountingCapability::ACTIVATE_BUDGET),
            'approveExpense' => $can(AccountingCapability::APPROVE_EXPENSE),
            'settlement' => $can(SettlementCapability::MANAGE),
            'assignProxy' => $authorization->canManageProductionDelegateRole($person, $production, RoleKey::participantManager())
                && $authorization->canManageProductionDelegateRole($person, $production, RoleKey::rehearsalManager()),
            'assignAccounting' => $authorization->canManageProductionDelegateRole($person, $production, RoleKey::accountingManager()),
            'assignCheckIn' => $authorization->canManageProductionDelegateRole($person, $production, RoleKey::checkInManager()),
            'assignTicket' => $authorization->canManageProductionDelegateRole($person, $production, RoleKey::ticketManager()),
            'complete' => $authorization->canCompleteProduction($person, $production),
            'lifecycle' => $authorization->canManageProduction($person, $production),
        ];
    }

    private function authorization(ProductionDelegateRepositoryInterface $delegates): ProductionAuthorizationService
    {
        return new ProductionAuthorizationService(
            new OrganizationAuthorizationService($this->people, new InMemoryMembershipRepository()),
            $delegates,
            new InMemoryParticipantRepository()
        );
    }

    private function givenPerson(int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        return $person;
    }

    /**
     * @param RoleKey[] $roles
     */
    private function givenRoles(string $who, int $wordPressUserId, array $roles): void
    {
        $this->person[$who] = $this->person[$who] ?? $this->givenPerson($wordPressUserId);

        foreach ($roles as $role) {
            $this->delegates->save(ProductionDelegate::create($this->production->id(), $this->person[$who]->id(), $role, $this->production->primaryManagerPersonId()));
        }
    }

    private function deactivate(string $who, RoleKey $role): void
    {
        $delegate = $this->delegates->findByProductionAndPersonAndRole($this->production->id(), $this->person[$who]->id(), $role);
        $delegate->deactivate($this->production->primaryManagerPersonId());
        $this->delegates->save($delegate);
    }
}

/**
 * The same rows, returned in the reverse order.
 */
final class ReversedProductionDelegateRepository implements ProductionDelegateRepositoryInterface
{
    private InMemoryProductionDelegateRepository $inner;

    public function __construct(InMemoryProductionDelegateRepository $inner)
    {
        $this->inner = $inner;
    }

    public function save(ProductionDelegate $delegate): void
    {
        $this->inner->save($delegate);
    }

    public function findById(ProductionDelegateId $id): ?ProductionDelegate
    {
        return $this->inner->findById($id);
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_reverse($this->inner->findByProductionId($productionId));
    }

    public function findByPersonId(PersonId $personId): array
    {
        return array_reverse($this->inner->findByPersonId($personId));
    }

    public function findByProductionAndPersonAndRole(ProductionId $productionId, PersonId $personId, RoleKey $role): ?ProductionDelegate
    {
        return $this->inner->findByProductionAndPersonAndRole($productionId, $personId, $role);
    }

    public function delete(ProductionDelegateId $id): void
    {
        $this->inner->delete($id);
    }
}
