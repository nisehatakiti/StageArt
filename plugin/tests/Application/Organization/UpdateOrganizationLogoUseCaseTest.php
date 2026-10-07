<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\Organization\UpdateOrganizationLogoCommand;
use StageArt\Application\Organization\UpdateOrganizationLogoUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class UpdateOrganizationLogoUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationRepository $organizations;
    private OrganizationAuthorizationService $authorization;
    private UpdateOrganizationLogoUseCase $useCase;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->useCase = new UpdateOrganizationLogoUseCase($this->organizations, $this->authorization);
    }

    private function givenOrganizationWithOwner(int $ownerWordPressUserId): Organization
    {
        $organization = Organization::create(new OrganizationName('Test Theatre'));
        $this->organizations->save($organization);

        $owner = Person::create($ownerWordPressUserId);
        $this->people->save($owner);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $owner->id()));

        return $organization;
    }

    public function test_owner_can_set_the_logo_url(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);

        $result = $this->useCase->execute(new UpdateOrganizationLogoCommand(
            $organization->id()->toString(),
            1,
            'https://dev-api.stageart.top/wp-content/uploads/2026/10/logo.png'
        ));

        $this->assertSame('https://dev-api.stageart.top/wp-content/uploads/2026/10/logo.png', $result->logoUrl);
    }

    public function test_owner_can_clear_the_logo_url(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $this->useCase->execute(new UpdateOrganizationLogoCommand($organization->id()->toString(), 1, 'https://example.com/logo.png'));

        $result = $this->useCase->execute(new UpdateOrganizationLogoCommand($organization->id()->toString(), 1, null));

        $this->assertNull($result->logoUrl);
    }

    public function test_delegate_can_update_the_logo(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $delegate = Person::create(2);
        $this->people->save($delegate);
        $this->memberships->save(Membership::create($organization->id(), $delegate->id(), RoleKey::organizationManager()));

        $result = $this->useCase->execute(new UpdateOrganizationLogoCommand(
            $organization->id()->toString(),
            2,
            'https://example.com/logo-by-delegate.png'
        ));

        $this->assertSame('https://example.com/logo-by-delegate.png', $result->logoUrl);
    }

    public function test_plain_member_cannot_update_the_logo(): void
    {
        $organization = $this->givenOrganizationWithOwner(1);
        $member = Person::create(3);
        $this->people->save($member);
        $this->memberships->save(Membership::create($organization->id(), $member->id(), RoleKey::member()));

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->useCase->execute(new UpdateOrganizationLogoCommand($organization->id()->toString(), 3, 'https://example.com/logo.png'));
    }

    public function test_unknown_organization_is_not_found(): void
    {
        // An orphaned Membership (Organization row missing) is the only
        // way to reach the not-found branch here at all: roleFor() is
        // checked before Organization existence (deliberately, so an
        // unrelated caller can never distinguish "no such Organization"
        // from "not your Organization" via a different status code).
        $owner = Person::create(1);
        $this->people->save($owner);
        $orphanOrganizationId = OrganizationId::generate();
        $this->memberships->save(Membership::createOwnerMembership($orphanOrganizationId, $owner->id()));

        $this->expectException(OrganizationNotFoundException::class);

        $this->useCase->execute(new UpdateOrganizationLogoCommand($orphanOrganizationId->toString(), 1, 'https://example.com/logo.png'));
    }

    public function test_unknown_organization_is_access_denied_when_the_requester_has_no_role_there(): void
    {
        $owner = Person::create(1);
        $this->people->save($owner);

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->useCase->execute(new UpdateOrganizationLogoCommand(OrganizationId::generate()->toString(), 1, 'https://example.com/logo.png'));
    }
}
