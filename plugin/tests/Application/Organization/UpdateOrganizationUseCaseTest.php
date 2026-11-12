<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\UpdateOrganizationCommand;
use StageArt\Application\Organization\UpdateOrganizationUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationStatus;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class UpdateOrganizationUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationRepository $organizations;
    private OrganizationAuthorizationService $authorization;
    private UpdateOrganizationUseCase $useCase;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->useCase = new UpdateOrganizationUseCase($this->organizations, $this->authorization);
    }

    private function givenOrganizationWithOwner(int $ownerWordPressUserId): Organization
    {
        $organization = Organization::create(new OrganizationName('Original Name'));
        $this->organizations->save($organization);

        $owner = Person::create($ownerWordPressUserId);
        $this->people->save($owner);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $owner->id()));

        return $organization;
    }

    public function test_owner_can_update_the_logo_url(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);

        $result = $this->useCase->execute(new UpdateOrganizationCommand(
            $organization->id()->toString(),
            1,
            'Original Name',
            null,
            null,
            OrganizationStatus::ACTIVE,
            null,
            null,
            null,
            'https://example.com/logo.png'
        ));

        $this->assertSame('https://example.com/logo.png', $result->logoUrl);
    }

    public function test_delegate_can_update_the_organization(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $delegate = Person::create(2);
        $this->people->save($delegate);
        $this->memberships->save(Membership::create($organization->id(), $delegate->id(), RoleKey::organizationManager()));

        $result = $this->useCase->execute(new UpdateOrganizationCommand(
            $organization->id()->toString(),
            2,
            'Updated By Delegate',
            null,
            null,
            OrganizationStatus::ACTIVE
        ));

        $this->assertSame('Updated By Delegate', $result->name);
        $this->assertSame(RoleKey::ORGANIZATION_MANAGER, $result->currentPersonRole);
    }

    public function test_plain_member_cannot_update_the_organization(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $member = Person::create(3);
        $this->people->save($member);
        $this->memberships->save(Membership::create($organization->id(), $member->id(), RoleKey::member()));

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->useCase->execute(new UpdateOrganizationCommand(
            $organization->id()->toString(),
            3,
            'Hacked Name',
            null,
            null,
            OrganizationStatus::ACTIVE
        ));
    }

    public function test_result_reports_the_owners_own_role_correctly(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);

        $result = $this->useCase->execute(new UpdateOrganizationCommand(
            $organization->id()->toString(),
            1,
            'Original Name',
            null,
            null,
            OrganizationStatus::ACTIVE
        ));

        $this->assertSame(RoleKey::OWNER, $result->currentPersonRole);
    }
}
