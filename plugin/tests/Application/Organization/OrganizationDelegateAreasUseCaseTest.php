<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\AssignOrganizationDelegateCommand;
use StageArt\Application\Organization\AssignOrganizationDelegateUseCase;
use StageArt\Application\Organization\ListOrganizationDelegateAreasQuery;
use StageArt\Application\Organization\ListOrganizationDelegateAreasUseCase;
use StageArt\Application\Organization\ListOrganizationsForPersonQuery;
use StageArt\Application\Organization\ListOrganizationsUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OwnerTransferCommand;
use StageArt\Application\Organization\OwnerTransferUseCase;
use StageArt\Application\Organization\RevokeOrganizationDelegateCommand;
use StageArt\Application\Organization\RevokeOrganizationDelegateUseCase;
use StageArt\Application\Organization\UpdateOrganizationDelegateAreasCommand;
use StageArt\Application\Organization\UpdateOrganizationDelegateAreasUseCase;
use StageArt\Domain\Membership\DelegateArea;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * 代理人の管理領域の設定 (docs/03-PublicPageURLAndPublicationSchedule.md
 * 「代理人設定」): who may read/change a delegate's areas, which values are
 * accepted, and that the stored areas follow the delegate's Membership.
 */
final class OrganizationDelegateAreasUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationRepository $organizations;
    private InMemoryUserAccountRepository $userAccounts;
    private OrganizationAuthorizationService $authorization;
    private UpdateOrganizationDelegateAreasUseCase $updateAreas;
    private ListOrganizationDelegateAreasUseCase $listAreas;
    private AssignOrganizationDelegateUseCase $assign;
    private RevokeOrganizationDelegateUseCase $revoke;
    private OrganizationId $organizationId;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->updateAreas = new UpdateOrganizationDelegateAreasUseCase($this->memberships, $this->authorization);
        $this->listAreas = new ListOrganizationDelegateAreasUseCase($this->memberships, $this->authorization);
        $this->assign = new AssignOrganizationDelegateUseCase($this->memberships, $this->authorization);
        $this->revoke = new RevokeOrganizationDelegateUseCase($this->memberships, $this->authorization);

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);
        $this->organizationId = $organization->id();
    }

    private function givenPerson(int $wordPressUserId, ?RoleKey $role, ?OrganizationId $organizationId = null): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->userAccounts->save(UserAccount::create($person->id()));

        if ($role !== null) {
            $this->memberships->save(Membership::create($organizationId ?? $this->organizationId, $person->id(), $role));
        }

        return $person;
    }

    private function givenOwner(int $wordPressUserId): Person
    {
        return $this->givenPerson($wordPressUserId, RoleKey::owner());
    }

    private function givenDelegate(int $wordPressUserId): Person
    {
        return $this->givenPerson($wordPressUserId, RoleKey::organizationManager());
    }

    /** @param mixed[] $areas */
    private function setAreas(int $requestedBy, Person $target, array $areas): array
    {
        return $this->updateAreas->execute(new UpdateOrganizationDelegateAreasCommand(
            $this->organizationId->toString(),
            $requestedBy,
            $target->id()->toString(),
            $areas
        ))->areas;
    }

    /** @return string[] areas as stored, read back from the repository */
    private function storedAreas(Person $person): array
    {
        return $this->memberships->findByOrganizationAndPerson($this->organizationId, $person->id())->delegateAreas();
    }

    public function test_owner_can_give_one_delegate_several_areas_and_they_are_stored(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);

        $returned = $this->setAreas(1, $delegate, [DelegateArea::ACCOUNTING, DelegateArea::MEMBERS]);

        $this->assertSame([DelegateArea::MEMBERS, DelegateArea::ACCOUNTING], $returned);
        $this->assertSame([DelegateArea::MEMBERS, DelegateArea::ACCOUNTING], $this->storedAreas($delegate));
    }

    public function test_the_same_area_can_be_given_to_several_delegates(): void
    {
        $this->givenOwner(1);
        $first = $this->givenDelegate(2);
        $second = $this->givenDelegate(3);

        $this->setAreas(1, $first, [DelegateArea::PRODUCTIONS]);
        $this->setAreas(1, $second, [DelegateArea::PRODUCTIONS, DelegateArea::MEMBERS]);

        $this->assertSame([DelegateArea::PRODUCTIONS], $this->storedAreas($first));
        $this->assertSame([DelegateArea::MEMBERS, DelegateArea::PRODUCTIONS], $this->storedAreas($second));
    }

    public function test_areas_can_be_removed_one_at_a_time_and_all_at_once(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);
        $this->setAreas(1, $delegate, DelegateArea::all());

        $this->setAreas(1, $delegate, [DelegateArea::MEMBERS, DelegateArea::ACCOUNTING]);
        $this->assertSame([DelegateArea::MEMBERS, DelegateArea::ACCOUNTING], $this->storedAreas($delegate));

        $this->setAreas(1, $delegate, []);
        $this->assertSame([], $this->storedAreas($delegate));
    }

    public function test_owner_can_read_every_delegates_areas_and_nobody_else_is_listed(): void
    {
        $this->givenOwner(1);
        $first = $this->givenDelegate(2);
        $second = $this->givenDelegate(3);
        $this->givenPerson(4, RoleKey::member());
        $this->setAreas(1, $first, [DelegateArea::MEMBERS]);

        $results = $this->listAreas->execute(new ListOrganizationDelegateAreasQuery($this->organizationId->toString(), 1));

        $byPerson = [];
        foreach ($results as $result) {
            $byPerson[$result->personId] = $result->areas;
        }
        $this->assertSame([
            $first->id()->toString() => [DelegateArea::MEMBERS],
            $second->id()->toString() => [],
        ], $byPerson);
        $this->assertSame(['person_id', 'areas'], array_keys($results[0]->toArray()));
    }

    public function test_a_delegate_cannot_widen_their_own_areas(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);
        $this->setAreas(1, $delegate, [DelegateArea::MEMBERS]);

        try {
            $this->setAreas(2, $delegate, DelegateArea::all());
            $this->fail('A delegate must not be able to change their own areas.');
        } catch (OrganizationAccessDeniedException $expected) {
        }

        $this->assertSame([DelegateArea::MEMBERS], $this->storedAreas($delegate));
    }

    public function test_a_delegate_holding_every_area_still_cannot_set_another_delegates_areas(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);
        $other = $this->givenDelegate(3);
        $this->setAreas(1, $delegate, DelegateArea::all());

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->setAreas(2, $other, [DelegateArea::ACCOUNTING]);
    }

    public function test_a_plain_member_cannot_set_areas(): void
    {
        $this->givenOwner(1);
        $this->givenPerson(2, RoleKey::member());
        $delegate = $this->givenDelegate(3);

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->setAreas(2, $delegate, [DelegateArea::MEMBERS]);
    }

    public function test_an_outsider_cannot_set_areas(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);
        $this->givenPerson(9, RoleKey::owner(), OrganizationId::generate());

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->setAreas(9, $delegate, [DelegateArea::MEMBERS]);
    }

    public function test_only_the_owner_can_read_delegate_areas(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);
        $this->setAreas(1, $delegate, DelegateArea::all());
        $this->givenPerson(3, RoleKey::member());
        $this->givenPerson(9, RoleKey::owner(), OrganizationId::generate());

        foreach ([2, 3, 9] as $requester) {
            try {
                $this->listAreas->execute(new ListOrganizationDelegateAreasQuery($this->organizationId->toString(), $requester));
                $this->fail("WordPress user {$requester} must not read delegate areas.");
            } catch (OrganizationAccessDeniedException $expected) {
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_areas_cannot_be_set_on_a_plain_member(): void
    {
        $this->givenOwner(1);
        $member = $this->givenPerson(2, RoleKey::member());

        $this->expectException(InvalidArgumentException::class);

        $this->setAreas(1, $member, [DelegateArea::MEMBERS]);
    }

    public function test_areas_cannot_be_set_on_the_owner(): void
    {
        $owner = $this->givenOwner(1);

        $this->expectException(InvalidArgumentException::class);

        $this->setAreas(1, $owner, [DelegateArea::MEMBERS]);
    }

    public function test_areas_cannot_be_set_on_someone_outside_the_organization(): void
    {
        $this->givenOwner(1);
        $outsider = $this->givenPerson(9, RoleKey::organizationManager(), OrganizationId::generate());

        $this->expectException(InvalidArgumentException::class);

        $this->setAreas(1, $outsider, [DelegateArea::MEMBERS]);
    }

    public function test_areas_cannot_be_set_on_a_requested_membership(): void
    {
        $this->givenOwner(1);
        $requester = $this->givenPerson(5, null);
        $this->memberships->save(Membership::requestMembership($this->organizationId, $requester->id()));

        $this->expectException(InvalidArgumentException::class);

        $this->setAreas(1, $requester, [DelegateArea::MEMBERS]);
    }

    public function test_an_unknown_area_is_refused_and_nothing_changes(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);
        $this->setAreas(1, $delegate, [DelegateArea::MEMBERS]);

        foreach ([['OWNER_TRANSFER'], ['members'], [DelegateArea::MEMBERS, 'ORGANIZATION_INFO'], [1], [null]] as $areas) {
            try {
                $this->setAreas(1, $delegate, $areas);
                $this->fail('Unknown area accepted: ' . json_encode($areas));
            } catch (InvalidArgumentException $expected) {
            }
        }

        $this->assertSame([DelegateArea::MEMBERS], $this->storedAreas($delegate));
    }

    public function test_a_repeated_area_is_refused(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);

        $this->expectException(InvalidArgumentException::class);

        $this->setAreas(1, $delegate, [DelegateArea::MEMBERS, DelegateArea::MEMBERS]);
    }

    public function test_a_newly_assigned_delegate_starts_with_no_areas(): void
    {
        $this->givenOwner(1);
        $member = $this->givenPerson(2, RoleKey::member());

        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 1, $member->id()->toString()));

        $this->assertSame([], $this->storedAreas($member));
    }

    public function test_revoking_a_delegate_drops_their_areas_and_reassigning_does_not_bring_them_back(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);
        $this->setAreas(1, $delegate, DelegateArea::all());

        $this->revoke->execute(new RevokeOrganizationDelegateCommand($this->organizationId->toString(), 1, $delegate->id()->toString()));
        $this->assertSame([], $this->storedAreas($delegate));

        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 1, $delegate->id()->toString()));
        $this->assertSame([], $this->storedAreas($delegate));
    }

    public function test_a_delegate_who_becomes_owner_keeps_no_stored_areas(): void
    {
        $owner = $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);
        $this->setAreas(1, $delegate, [DelegateArea::MEMBERS]);
        $transfer = new OwnerTransferUseCase($this->memberships, $this->userAccounts, $this->authorization, new InMemoryTransactionManager());

        $transfer->execute(new OwnerTransferCommand($this->organizationId->toString(), 1, $delegate->id()->toString()));

        $this->assertSame([], $this->storedAreas($delegate));
        $this->assertSame([], $this->storedAreas($owner));
        $this->assertSame(DelegateArea::all(), $this->authorization->managementAreasFor($delegate, $this->organizationId));
        $this->assertSame([], $this->authorization->managementAreasFor($owner, $this->organizationId));
    }

    public function test_management_areas_per_role(): void
    {
        $owner = $this->givenOwner(1);
        $delegate = $this->givenDelegate(2);
        $bareDelegate = $this->givenDelegate(3);
        $member = $this->givenPerson(4, RoleKey::member());
        $outsider = $this->givenPerson(9, RoleKey::owner(), OrganizationId::generate());
        $this->setAreas(1, $delegate, [DelegateArea::PRODUCTIONS, DelegateArea::ACCOUNTING]);

        $this->assertSame(DelegateArea::all(), $this->authorization->managementAreasFor($owner, $this->organizationId));
        $this->assertSame([DelegateArea::PRODUCTIONS, DelegateArea::ACCOUNTING], $this->authorization->managementAreasFor($delegate, $this->organizationId));
        $this->assertSame([], $this->authorization->managementAreasFor($bareDelegate, $this->organizationId));
        $this->assertSame([], $this->authorization->managementAreasFor($member, $this->organizationId));
        $this->assertSame([], $this->authorization->managementAreasFor($outsider, $this->organizationId));

        $this->assertTrue($this->authorization->hasDelegateArea($delegate, $this->organizationId, DelegateArea::ACCOUNTING));
        $this->assertFalse($this->authorization->hasDelegateArea($delegate, $this->organizationId, DelegateArea::MEMBERS));
        $this->assertFalse($this->authorization->hasDelegateArea($member, $this->organizationId, DelegateArea::MEMBERS));
        $this->assertFalse($this->authorization->hasDelegateArea($outsider, $this->organizationId, DelegateArea::MEMBERS));
    }

    public function test_each_organization_in_the_list_carries_only_its_own_areas(): void
    {
        $person = $this->givenDelegate(2);
        $this->givenOwner(1);
        $this->setAreas(1, $person, [DelegateArea::ACCOUNTING]);

        $other = Organization::create(new OrganizationName('Other Co'));
        $this->organizations->save($other);
        $this->memberships->save(Membership::create($other->id(), $person->id(), RoleKey::member()));
        $owned = Organization::create(new OrganizationName('Own Co'));
        $this->organizations->save($owned);
        $this->memberships->save(Membership::createOwnerMembership($owned->id(), $person->id()));

        $list = new ListOrganizationsUseCase($this->organizations, $this->memberships, $this->authorization);
        $areasById = [];
        foreach ($list->execute(new ListOrganizationsForPersonQuery(2)) as $result) {
            $areasById[$result->id] = $result->toArray()['current_person_management_areas'];
        }

        $this->assertSame([DelegateArea::ACCOUNTING], $areasById[$this->organizationId->toString()]);
        $this->assertSame([], $areasById[$other->id()->toString()]);
        $this->assertSame(DelegateArea::all(), $areasById[$owned->id()->toString()]);
    }
}
