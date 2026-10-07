<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\AssignOrganizationDelegateCommand;
use StageArt\Application\Organization\AssignOrganizationDelegateUseCase;
use StageArt\Application\Organization\ListOrganizationMembersQuery;
use StageArt\Application\Organization\ListOrganizationMembersUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\RevokeOrganizationDelegateCommand;
use StageArt\Application\Organization\RevokeOrganizationDelegateUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class OrganizationDelegateUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private OrganizationAuthorizationService $authorization;
    private AssignOrganizationDelegateUseCase $assign;
    private RevokeOrganizationDelegateUseCase $revoke;
    private ListOrganizationMembersUseCase $listMembers;
    private OrganizationId $organizationId;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->assign = new AssignOrganizationDelegateUseCase($this->memberships, $this->authorization);
        $this->revoke = new RevokeOrganizationDelegateUseCase($this->memberships, $this->authorization);
        $this->listMembers = new ListOrganizationMembersUseCase($this->memberships, $this->people, $this->authorization);
        $this->organizationId = OrganizationId::generate();
    }

    private function givenOwner(int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->memberships->save(Membership::createOwnerMembership($this->organizationId, $person->id()));

        return $person;
    }

    private function givenPlainMember(int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->memberships->save(Membership::create($this->organizationId, $person->id(), RoleKey::member()));

        return $person;
    }

    public function test_owner_can_assign_a_delegate(): void
    {
        $this->givenOwner(1);
        $member = $this->givenPlainMember(2);

        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 1, $member->id()->toString()));

        $membership = $this->memberships->findByOrganizationAndPerson($this->organizationId, $member->id());
        $this->assertSame(RoleKey::ORGANIZATION_MANAGER, $membership->roleKey()->toString());
    }

    public function test_a_delegate_cannot_assign_another_delegate(): void
    {
        $this->givenOwner(1);
        $delegate = $this->givenPlainMember(2);
        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 1, $delegate->id()->toString()));
        $anotherMember = $this->givenPlainMember(3);

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 2, $anotherMember->id()->toString()));
    }

    public function test_a_plain_member_cannot_assign_a_delegate(): void
    {
        $this->givenOwner(1);
        $requester = $this->givenPlainMember(2);
        $target = $this->givenPlainMember(3);

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 2, $target->id()->toString()));
    }

    public function test_assigning_a_delegate_requires_an_active_membership_in_this_organization(): void
    {
        $this->givenOwner(1);
        $outsider = Person::create(99);
        $this->people->save($outsider);

        $this->expectException(InvalidArgumentException::class);

        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 1, $outsider->id()->toString()));
    }

    public function test_the_owner_cannot_also_be_assigned_as_a_delegate(): void
    {
        $owner = $this->givenOwner(1);

        $this->expectException(InvalidArgumentException::class);

        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 1, $owner->id()->toString()));
    }

    public function test_owner_can_revoke_a_delegate(): void
    {
        $this->givenOwner(1);
        $member = $this->givenPlainMember(2);
        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 1, $member->id()->toString()));

        $this->revoke->execute(new RevokeOrganizationDelegateCommand($this->organizationId->toString(), 1, $member->id()->toString()));

        $membership = $this->memberships->findByOrganizationAndPerson($this->organizationId, $member->id());
        $this->assertSame(RoleKey::MEMBER, $membership->roleKey()->toString());
    }

    public function test_revoking_a_non_delegate_is_rejected(): void
    {
        $this->givenOwner(1);
        $member = $this->givenPlainMember(2);

        $this->expectException(InvalidArgumentException::class);

        $this->revoke->execute(new RevokeOrganizationDelegateCommand($this->organizationId->toString(), 1, $member->id()->toString()));
    }

    public function test_list_members_returns_every_active_membership_with_person_names(): void
    {
        $owner = $this->givenOwner(1);
        $owner->setName('山田', '太郎');
        $this->people->save($owner);
        $this->givenPlainMember(2);

        $results = $this->listMembers->execute(new ListOrganizationMembersQuery($this->organizationId->toString(), 1));

        $this->assertCount(2, $results);
        $ownerResult = current(array_filter($results, fn ($r) => $r->roleKey === RoleKey::OWNER));
        $this->assertSame('山田', $ownerResult->personFamilyName);
    }

    public function test_list_members_excludes_requested_memberships(): void
    {
        $this->givenOwner(1);
        $requester = Person::create(5);
        $this->people->save($requester);
        $this->memberships->save(Membership::requestMembership($this->organizationId, $requester->id()));

        $results = $this->listMembers->execute(new ListOrganizationMembersQuery($this->organizationId->toString(), 1));

        $this->assertCount(1, $results);
    }

    public function test_plain_member_cannot_list_members(): void
    {
        $this->givenOwner(1);
        $member = $this->givenPlainMember(2);

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->listMembers->execute(new ListOrganizationMembersQuery($this->organizationId->toString(), 2));
    }

    public function test_delegate_can_list_members(): void
    {
        $this->givenOwner(1);
        $member = $this->givenPlainMember(2);
        $this->assign->execute(new AssignOrganizationDelegateCommand($this->organizationId->toString(), 1, $member->id()->toString()));

        $results = $this->listMembers->execute(new ListOrganizationMembersQuery($this->organizationId->toString(), 2));

        $this->assertCount(2, $results);
    }
}
