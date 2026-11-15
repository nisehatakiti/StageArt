<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationMemberNotFoundException;
use StageArt\Application\Organization\UpdateOrganizationMemberRemarksCommand;
use StageArt\Application\Organization\UpdateOrganizationMemberRemarksUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class UpdateOrganizationMemberRemarksUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private OrganizationAuthorizationService $authorization;
    private UpdateOrganizationMemberRemarksUseCase $useCase;
    private OrganizationId $organizationId;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->useCase = new UpdateOrganizationMemberRemarksUseCase($this->memberships, $this->authorization);
        $this->organizationId = OrganizationId::generate();
    }

    private function givenOwner(int $wordPressUserId): Person
    {
        $owner = Person::create($wordPressUserId);
        $this->people->save($owner);
        $this->memberships->save(Membership::createOwnerMembership($this->organizationId, $owner->id()));

        return $owner;
    }

    private function givenMember(int $wordPressUserId): Person
    {
        $member = Person::create($wordPressUserId);
        $this->people->save($member);
        $this->memberships->save(Membership::create($this->organizationId, $member->id(), RoleKey::member()));

        return $member;
    }

    public function test_owner_can_update_a_members_remarks(): void
    {
        $this->givenOwner(1);
        $member = $this->givenMember(2);

        $result = $this->useCase->execute(new UpdateOrganizationMemberRemarksCommand(
            $this->organizationId->toString(),
            $member->id()->toString(),
            1,
            '衣装合わせ待ち'
        ));

        $this->assertSame('衣装合わせ待ち', $result->remarks);
        $membership = $this->memberships->findByOrganizationAndPerson($this->organizationId, $member->id());
        $this->assertSame('衣装合わせ待ち', $membership->remarks());
    }

    public function test_remarks_can_be_cleared(): void
    {
        $this->givenOwner(1);
        $member = $this->givenMember(2);
        $this->useCase->execute(new UpdateOrganizationMemberRemarksCommand($this->organizationId->toString(), $member->id()->toString(), 1, '一時メモ'));

        $result = $this->useCase->execute(new UpdateOrganizationMemberRemarksCommand($this->organizationId->toString(), $member->id()->toString(), 1, null));

        $this->assertNull($result->remarks);
    }

    public function test_plain_member_cannot_update_another_members_remarks(): void
    {
        $this->givenOwner(1);
        $member = $this->givenMember(2);
        $other = $this->givenMember(3);

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->useCase->execute(new UpdateOrganizationMemberRemarksCommand($this->organizationId->toString(), $member->id()->toString(), 3, 'hacked'));
    }

    public function test_unknown_member_is_not_found(): void
    {
        $this->givenOwner(1);

        $this->expectException(OrganizationMemberNotFoundException::class);

        $this->useCase->execute(new UpdateOrganizationMemberRemarksCommand(
            $this->organizationId->toString(),
            PersonId::generate()->toString(),
            1,
            'no such member'
        ));
    }
}
