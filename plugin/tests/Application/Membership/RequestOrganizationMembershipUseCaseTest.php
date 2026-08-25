<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Membership;

use PHPUnit\Framework\TestCase;
use StageArt\Application\JoinKey\JoinKeyNotFoundException;
use StageArt\Application\Membership\MembershipAlreadyExistsException;
use StageArt\Application\Membership\RequestOrganizationMembershipCommand;
use StageArt\Application\Membership\RequestOrganizationMembershipUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryJoinKeyRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class RequestOrganizationMembershipUseCaseTest extends TestCase
{
    private function useCase(
        InMemoryOrganizationRepository $organizations,
        InMemoryMembershipRepository $memberships,
        InMemoryJoinKeyRepository $joinKeys,
        InMemoryPersonRepository $people
    ): RequestOrganizationMembershipUseCase {
        return new RequestOrganizationMembershipUseCase(
            $organizations,
            $memberships,
            $joinKeys,
            new OrganizationAuthorizationService($people, $memberships)
        );
    }

    private function givenPublishedOrganization(InMemoryOrganizationRepository $organizations): Organization
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, new OrganizationSlug('theatre-co'));
        $organization->publish();
        $organizations->save($organization);

        return $organization;
    }

    public function test_requesting_via_a_published_organization_id_creates_a_requested_membership(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $joinKeys = new InMemoryJoinKeyRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));

        $organization = $this->givenPublishedOrganization($organizations);

        $result = $this->useCase($organizations, $memberships, $joinKeys, $people)->execute(
            new RequestOrganizationMembershipCommand(1, $organization->id()->toString(), null)
        );

        $this->assertSame(Membership::STATUS_REQUESTED, $result->status);
    }

    public function test_requesting_via_an_unpublished_organization_id_is_not_found(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $joinKeys = new InMemoryJoinKeyRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));

        $organization = Organization::create(new OrganizationName('Draft Co'));
        $organizations->save($organization);

        $this->expectException(OrganizationNotFoundException::class);

        $this->useCase($organizations, $memberships, $joinKeys, $people)->execute(
            new RequestOrganizationMembershipCommand(1, $organization->id()->toString(), null)
        );
    }

    public function test_requesting_via_a_join_key_works_even_for_an_unpublished_organization_and_consumes_a_use(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $joinKeys = new InMemoryJoinKeyRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $owner = Person::create(99);
        $people->save($owner);

        $organization = Organization::create(new OrganizationName('Unpublished Co'));
        $organizations->save($organization);

        $joinKey = JoinKey::issueForOrganization($organization->id()->toString(), $owner->id());
        $joinKeys->save($joinKey);

        $result = $this->useCase($organizations, $memberships, $joinKeys, $people)->execute(
            new RequestOrganizationMembershipCommand(1, null, $joinKey->code())
        );

        $this->assertSame(Membership::STATUS_REQUESTED, $result->status);
        $this->assertSame(1, $joinKeys->findByCode($joinKey->code())->useCount());
    }

    public function test_requesting_with_an_unusable_join_key_code_throws(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $joinKeys = new InMemoryJoinKeyRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));

        $this->expectException(JoinKeyNotFoundException::class);

        $this->useCase($organizations, $memberships, $joinKeys, $people)->execute(
            new RequestOrganizationMembershipCommand(1, null, 'NOPE0000')
        );
    }

    public function test_requesting_twice_while_the_first_request_is_still_pending_is_rejected(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $joinKeys = new InMemoryJoinKeyRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));

        $organization = $this->givenPublishedOrganization($organizations);
        $useCase = $this->useCase($organizations, $memberships, $joinKeys, $people);

        $useCase->execute(new RequestOrganizationMembershipCommand(1, $organization->id()->toString(), null));

        $this->expectException(MembershipAlreadyExistsException::class);

        $useCase->execute(new RequestOrganizationMembershipCommand(1, $organization->id()->toString(), null));
    }

    public function test_requesting_again_after_a_rejection_is_allowed(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $joinKeys = new InMemoryJoinKeyRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));

        $organization = $this->givenPublishedOrganization($organizations);
        $useCase = $this->useCase($organizations, $memberships, $joinKeys, $people);

        $first = $useCase->execute(new RequestOrganizationMembershipCommand(1, $organization->id()->toString(), null));
        $memberships->findById(\StageArt\Domain\Membership\MembershipId::fromString($first->id))->reject();

        $second = $useCase->execute(new RequestOrganizationMembershipCommand(1, $organization->id()->toString(), null));

        $this->assertSame(Membership::STATUS_REQUESTED, $second->status);
        $this->assertNotSame($first->id, $second->id);
    }
}
