<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Membership;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Membership\ApproveMembershipRequestCommand;
use StageArt\Application\Membership\ApproveMembershipRequestUseCase;
use StageArt\Application\Membership\ListMyMembershipsQuery;
use StageArt\Application\Membership\ListMyMembershipsUseCase;
use StageArt\Application\Membership\ListPendingMembershipRequestsQuery;
use StageArt\Application\Membership\ListPendingMembershipRequestsUseCase;
use StageArt\Application\Membership\MembershipAccessDeniedException;
use StageArt\Application\Membership\RejectMembershipRequestCommand;
use StageArt\Application\Membership\RejectMembershipRequestUseCase;
use StageArt\Application\Membership\RequestOrganizationMembershipCommand;
use StageArt\Application\Membership\RequestOrganizationMembershipUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryJoinKeyRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class MembershipApprovalUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryMembershipRepository $memberships;
    private InMemoryPersonRepository $people;
    private OrganizationAuthorizationService $authorization;
    private Organization $organization;
    private Person $owner;
    private Person $requester;
    private string $requestId;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->people = new InMemoryPersonRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);

        $this->organization = Organization::create(new OrganizationName('Theatre Co'), null, null, new OrganizationSlug('theatre-co'));
        $this->organization->publish();
        $this->organizations->save($this->organization);

        $this->owner = Person::create(1);
        $this->people->save($this->owner);
        $this->memberships->save(Membership::createOwnerMembership($this->organization->id(), $this->owner->id()));

        $this->requester = Person::create(2);
        $this->people->save($this->requester);

        $requestUseCase = new RequestOrganizationMembershipUseCase(
            $this->organizations,
            $this->memberships,
            new InMemoryJoinKeyRepository(),
            $this->authorization
        );
        $result = $requestUseCase->execute(new RequestOrganizationMembershipCommand(2, $this->organization->id()->toString(), null));
        $this->requestId = $result->id;
    }

    public function test_owner_can_approve_a_pending_request(): void
    {
        $useCase = new ApproveMembershipRequestUseCase($this->memberships, $this->people, $this->authorization);

        $result = $useCase->execute(new ApproveMembershipRequestCommand(1, $this->requestId));

        $this->assertSame(Membership::STATUS_ACTIVE, $result->status);
    }

    public function test_non_owner_cannot_approve(): void
    {
        $useCase = new ApproveMembershipRequestUseCase($this->memberships, $this->people, $this->authorization);

        $this->expectException(MembershipAccessDeniedException::class);

        $useCase->execute(new ApproveMembershipRequestCommand(2, $this->requestId));
    }

    public function test_owner_can_reject_a_pending_request(): void
    {
        $useCase = new RejectMembershipRequestUseCase($this->memberships, $this->people, $this->authorization);

        $result = $useCase->execute(new RejectMembershipRequestCommand(1, $this->requestId));

        $this->assertSame(Membership::STATUS_REJECTED, $result->status);
    }

    public function test_pending_requests_list_shows_the_requester_and_excludes_approved_ones(): void
    {
        $listUseCase = new ListPendingMembershipRequestsUseCase($this->memberships, $this->people, $this->authorization);

        $before = $listUseCase->execute(new ListPendingMembershipRequestsQuery(1, $this->organization->id()->toString()));
        $this->assertCount(1, $before);
        $this->assertSame($this->requester->id()->toString(), $before[0]->personId);

        (new ApproveMembershipRequestUseCase($this->memberships, $this->people, $this->authorization))->execute(
            new ApproveMembershipRequestCommand(1, $this->requestId)
        );

        $after = $listUseCase->execute(new ListPendingMembershipRequestsQuery(1, $this->organization->id()->toString()));
        $this->assertCount(0, $after);
    }

    public function test_my_memberships_reflects_the_current_status(): void
    {
        $useCase = new ListMyMembershipsUseCase($this->memberships, $this->organizations, $this->authorization);

        $before = $useCase->execute(new ListMyMembershipsQuery(2));
        $this->assertCount(1, $before);
        $this->assertSame(Membership::STATUS_REQUESTED, $before[0]->status);

        (new ApproveMembershipRequestUseCase($this->memberships, $this->people, $this->authorization))->execute(
            new ApproveMembershipRequestCommand(1, $this->requestId)
        );

        $after = $useCase->execute(new ListMyMembershipsQuery(2));
        $this->assertSame(Membership::STATUS_ACTIVE, $after[0]->status);
    }
}
