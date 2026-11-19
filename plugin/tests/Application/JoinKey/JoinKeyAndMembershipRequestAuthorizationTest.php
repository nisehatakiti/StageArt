<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\JoinKey;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\JoinKey\DisableJoinKeyCommand;
use StageArt\Application\JoinKey\DisableJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyCommand;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueProductionJoinKeyCommand;
use StageArt\Application\JoinKey\IssueProductionJoinKeyUseCase;
use StageArt\Application\JoinKey\JoinKeyAccessDeniedException;
use StageArt\Application\JoinKey\JoinKeyResult;
use StageArt\Application\JoinKey\ListOrganizationJoinKeysQuery;
use StageArt\Application\JoinKey\ListOrganizationJoinKeysUseCase;
use StageArt\Application\JoinKey\ListProductionJoinKeysQuery;
use StageArt\Application\JoinKey\ListProductionJoinKeysUseCase;
use StageArt\Application\Membership\ApproveMembershipRequestCommand;
use StageArt\Application\Membership\ApproveMembershipRequestUseCase;
use StageArt\Application\Membership\ListPendingMembershipRequestsQuery;
use StageArt\Application\Membership\ListPendingMembershipRequestsUseCase;
use StageArt\Application\Membership\MembershipAccessDeniedException;
use StageArt\Application\Membership\MembershipRequestResult;
use StageArt\Application\Membership\RejectMembershipRequestCommand;
use StageArt\Application\Membership\RejectMembershipRequestUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\Membership\DelegateArea;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipId;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryJoinKeyRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use Throwable;

/**
 * 参加コード (Join Key) と参加申請 の認可:
 * - Organization side (list / issue / disable keys, list / approve /
 *   reject requests): the Owner or a delegate holding メンバー管理
 *   (DelegateArea::MEMBERS); a delegate without it, a plain member, an
 *   outsider and an unlinked / signed-out user are refused.
 * - Production side (list / issue / disable keys): the Production's
 *   PrimaryManager only; no Organization role or area grants it.
 * - Ids of another Organization / Production never pass.
 *
 * WordPress user ids: 1 Owner of A, 2 delegate of A (areas per test),
 * 3 plain member of A, 4 PrimaryManager of Production P (a plain member
 * of A), 5 代理人 on P (PARTICIPANT_MANAGER + REHEARSAL_MANAGER),
 * 9 outsider (Owner of B and PrimaryManager of Production Q in B),
 * 0 no linked Person (signed out).
 */
final class JoinKeyAndMembershipRequestAuthorizationTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationRepository $organizations;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $productionDelegates;
    private InMemoryJoinKeyRepository $joinKeys;
    private OrganizationAuthorizationService $authorization;
    private ProductionAuthorizationService $productionAuthorization;
    private Organization $organization;
    private Organization $otherOrganization;
    private Production $production;
    private Production $otherProduction;
    private Person $delegate;
    private string $requestId;
    private string $otherOrganizationRequestId;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->productionDelegates = new InMemoryProductionDelegateRepository();
        $this->joinKeys = new InMemoryJoinKeyRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->productionAuthorization = new ProductionAuthorizationService(
            $this->authorization,
            $this->productionDelegates,
            new InMemoryParticipantRepository()
        );

        $this->organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($this->organization);
        $this->otherOrganization = Organization::create(new OrganizationName('Other Co'));
        $this->organizations->save($this->otherOrganization);

        $this->givenPerson(1, $this->organization->id(), RoleKey::owner());
        $this->delegate = $this->givenPerson(2, $this->organization->id(), RoleKey::organizationManager());
        $this->givenPerson(3, $this->organization->id(), RoleKey::member());
        $primaryManager = $this->givenPerson(4, $this->organization->id(), RoleKey::member());
        $productionProxy = $this->givenPerson(5, $this->organization->id(), RoleKey::member());
        $outsider = $this->givenPerson(9, $this->otherOrganization->id(), RoleKey::owner());

        $this->production = Production::create(
            Project::create($this->organization->id(), 'Bridge')->id(),
            new ProductionName('Show'),
            $primaryManager->id()
        );
        $this->productions->save($this->production);
        foreach ([RoleKey::participantManager(), RoleKey::rehearsalManager()] as $role) {
            $this->productionDelegates->save(ProductionDelegate::create($this->production->id(), $productionProxy->id(), $role, $primaryManager->id()));
        }

        $this->otherProduction = Production::create(
            Project::create($this->otherOrganization->id(), 'Other Bridge')->id(),
            new ProductionName('Other Show'),
            $outsider->id()
        );
        $this->productions->save($this->otherProduction);

        $this->requestId = $this->givenRequest(20, $this->organization->id());
        $this->otherOrganizationRequestId = $this->givenRequest(30, $this->otherOrganization->id());
    }

    private function givenPerson(int $wordPressUserId, OrganizationId $organizationId, RoleKey $role): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->memberships->save(Membership::create($organizationId, $person->id(), $role));

        return $person;
    }

    private function givenRequest(int $wordPressUserId, OrganizationId $organizationId): string
    {
        $requester = Person::create($wordPressUserId);
        $this->people->save($requester);
        $request = Membership::requestMembership($organizationId, $requester->id());
        $this->memberships->save($request);

        return $request->id()->toString();
    }

    /** @param string[] $areas */
    private function delegateHolds(array $areas): void
    {
        $membership = $this->memberships->findByOrganizationAndPerson($this->organization->id(), $this->delegate->id());
        $membership->replaceDelegateAreas($areas);
        $this->memberships->save($membership);
    }

    /** @return string[] */
    private function allButMembers(): array
    {
        return array_values(array_diff(DelegateArea::all(), [DelegateArea::MEMBERS]));
    }

    private function organizationKey(): JoinKeyResult
    {
        return $this->issueOrganizationKey()->execute(new IssueOrganizationJoinKeyCommand(1, $this->organization->id()->toString()));
    }

    private function issueOrganizationKey(): IssueOrganizationJoinKeyUseCase
    {
        return new IssueOrganizationJoinKeyUseCase($this->organizations, $this->joinKeys, $this->authorization);
    }

    private function listOrganizationKeys(): ListOrganizationJoinKeysUseCase
    {
        return new ListOrganizationJoinKeysUseCase($this->organizations, $this->joinKeys, $this->authorization);
    }

    private function issueProductionKey(): IssueProductionJoinKeyUseCase
    {
        return new IssueProductionJoinKeyUseCase($this->productions, $this->joinKeys, $this->productionAuthorization);
    }

    private function listProductionKeys(): ListProductionJoinKeysUseCase
    {
        return new ListProductionJoinKeysUseCase($this->productions, $this->joinKeys, $this->productionAuthorization);
    }

    private function disableKey(): DisableJoinKeyUseCase
    {
        return new DisableJoinKeyUseCase($this->joinKeys, $this->productions, $this->authorization, $this->productionAuthorization);
    }

    private function listRequests(): ListPendingMembershipRequestsUseCase
    {
        return new ListPendingMembershipRequestsUseCase($this->memberships, $this->people, $this->authorization);
    }

    private function approveRequest(): ApproveMembershipRequestUseCase
    {
        return new ApproveMembershipRequestUseCase($this->memberships, $this->people, $this->authorization);
    }

    private function rejectRequest(): RejectMembershipRequestUseCase
    {
        return new RejectMembershipRequestUseCase($this->memberships, $this->people, $this->authorization);
    }

    private function requestStatus(string $id): string
    {
        return $this->memberships->findById(MembershipId::fromString($id))->status();
    }

    /** @param class-string<Throwable> $exception */
    private function assertRefused(string $exception, callable $operation, string $who): void
    {
        try {
            $operation();
        } catch (Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown, "{$who}: unexpected " . get_class($thrown) . ': ' . $thrown->getMessage());

            return;
        }

        $this->fail("{$who} must be refused.");
    }

    /** @return array<int, string> users refused on the Organization side, with the delegate lacking MEMBERS */
    private function refusedOrganizationUsers(): array
    {
        $this->delegateHolds($this->allButMembers());

        return [2 => 'delegate without MEMBERS', 3 => 'plain member', 4 => 'PrimaryManager (plain member)', 9 => 'outsider', 0 => 'signed out'];
    }

    // ---- 団体側の参加コード -----------------------------------------------

    public function test_owner_lists_issues_and_disables_organization_join_keys(): void
    {
        $organizationId = $this->organization->id()->toString();
        $issued = $this->organizationKey();

        $listed = $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(1, $organizationId));
        $this->assertSame([$issued->id], array_map(static fn (JoinKeyResult $key) => $key->id, $listed));
        $this->assertSame($issued->code, $listed[0]->code);

        $disabled = $this->disableKey()->execute(new DisableJoinKeyCommand(1, $issued->id));
        $this->assertSame(JoinKey::STATUS_DISABLED, $disabled->status);
        $this->assertSame(JoinKey::STATUS_DISABLED, $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(1, $organizationId))[0]->status);
    }

    public function test_delegate_with_members_lists_issues_and_disables_organization_join_keys(): void
    {
        $this->delegateHolds([DelegateArea::MEMBERS]);
        $organizationId = $this->organization->id()->toString();

        $ownerKey = $this->organizationKey();
        $listed = $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(2, $organizationId));
        $this->assertSame([$ownerKey->id], array_map(static fn (JoinKeyResult $key) => $key->id, $listed));

        $issued = $this->issueOrganizationKey()->execute(new IssueOrganizationJoinKeyCommand(2, $organizationId));
        $this->assertSame(JoinKey::TARGET_TYPE_ORGANIZATION, $issued->targetType);
        $this->assertSame($organizationId, $issued->targetId);

        $this->assertSame(JoinKey::STATUS_DISABLED, $this->disableKey()->execute(new DisableJoinKeyCommand(2, $issued->id))->status);
        $this->assertSame(JoinKey::STATUS_DISABLED, $this->disableKey()->execute(new DisableJoinKeyCommand(2, $ownerKey->id))->status);
    }

    public function test_everyone_else_is_refused_every_organization_join_key_operation(): void
    {
        $organizationId = $this->organization->id()->toString();
        $issued = $this->organizationKey();

        foreach ($this->refusedOrganizationUsers() as $user => $who) {
            $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery($user, $organizationId)), "{$who}: list");
            $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $this->issueOrganizationKey()->execute(new IssueOrganizationJoinKeyCommand($user, $organizationId)), "{$who}: issue");
            $this->assertRefused(JoinKeyAccessDeniedException::class, fn () => $this->disableKey()->execute(new DisableJoinKeyCommand($user, $issued->id)), "{$who}: disable");
        }

        // Nothing was issued or disabled by the refused calls.
        $keys = $this->joinKeys->findByTarget(JoinKey::TARGET_TYPE_ORGANIZATION, $organizationId);
        $this->assertCount(1, $keys);
        $this->assertSame(JoinKey::STATUS_ACTIVE, array_values($keys)[0]->status());
    }

    public function test_a_members_delegate_gets_nothing_from_another_organization(): void
    {
        $this->delegateHolds(DelegateArea::all());
        $otherOrganizationId = $this->otherOrganization->id()->toString();
        $otherKey = $this->issueOrganizationKey()->execute(new IssueOrganizationJoinKeyCommand(9, $otherOrganizationId));

        $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(2, $otherOrganizationId)), 'list B');
        $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $this->issueOrganizationKey()->execute(new IssueOrganizationJoinKeyCommand(2, $otherOrganizationId)), 'issue B');
        $this->assertRefused(JoinKeyAccessDeniedException::class, fn () => $this->disableKey()->execute(new DisableJoinKeyCommand(2, $otherKey->id)), 'disable B key');

        // B's Owner, likewise, gets nothing from A.
        $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(9, $this->organization->id()->toString())), 'B owner lists A');
        $this->assertSame(JoinKey::STATUS_ACTIVE, $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(9, $otherOrganizationId))[0]->status);
    }

    public function test_listing_an_unknown_organization_is_refused_to_outsiders_and_not_found_otherwise(): void
    {
        $unknown = OrganizationId::generate()->toString();

        // No Membership in an unknown Organization -> refused before its existence is revealed.
        $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(1, $unknown)), 'unknown organization');

        // An Organization whose Membership still exists but whose record is gone -> 404.
        $this->organizations = new InMemoryOrganizationRepository();
        $this->assertRefused(OrganizationNotFoundException::class, fn () => $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(1, $this->organization->id()->toString())), 'missing organization');
    }

    public function test_removing_members_from_a_delegate_closes_join_keys_again(): void
    {
        $organizationId = $this->organization->id()->toString();
        $this->delegateHolds([DelegateArea::MEMBERS]);
        $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(2, $organizationId));

        $this->delegateHolds([]);
        $this->assertRefused(OrganizationAccessDeniedException::class, fn () => $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(2, $organizationId)), 'after removal');
    }

    // ---- 参加申請 ----------------------------------------------------------

    public function test_owner_lists_and_approves_a_request(): void
    {
        $listed = $this->listRequests()->execute(new ListPendingMembershipRequestsQuery(1, $this->organization->id()->toString()));
        $this->assertSame([$this->requestId], array_map(static fn (MembershipRequestResult $request) => $request->id, $listed));

        $this->approveRequest()->execute(new ApproveMembershipRequestCommand(1, $this->requestId));
        $this->assertSame(Membership::STATUS_ACTIVE, $this->requestStatus($this->requestId));
        $this->assertSame([], $this->listRequests()->execute(new ListPendingMembershipRequestsQuery(1, $this->organization->id()->toString())));

        // An approved request cannot be approved or rejected again.
        $this->assertRefused(InvalidArgumentException::class, fn () => $this->approveRequest()->execute(new ApproveMembershipRequestCommand(1, $this->requestId)), 'approve twice');
        $this->assertRefused(InvalidArgumentException::class, fn () => $this->rejectRequest()->execute(new RejectMembershipRequestCommand(1, $this->requestId)), 'reject after approval');
    }

    public function test_delegate_with_members_lists_approves_and_rejects_requests(): void
    {
        $this->delegateHolds([DelegateArea::MEMBERS]);
        $second = $this->givenRequest(21, $this->organization->id());

        $listed = $this->listRequests()->execute(new ListPendingMembershipRequestsQuery(2, $this->organization->id()->toString()));
        $ids = array_map(static fn (MembershipRequestResult $request) => $request->id, $listed);
        sort($ids);
        $expected = [$this->requestId, $second];
        sort($expected);
        $this->assertSame($expected, $ids);

        $this->approveRequest()->execute(new ApproveMembershipRequestCommand(2, $this->requestId));
        $this->rejectRequest()->execute(new RejectMembershipRequestCommand(2, $second));

        $this->assertSame(Membership::STATUS_ACTIVE, $this->requestStatus($this->requestId));
        $this->assertSame(Membership::STATUS_REJECTED, $this->requestStatus($second));
        // Approval makes a plain member, never a delegate.
        $this->assertSame(RoleKey::MEMBER, $this->memberships->findById(MembershipId::fromString($this->requestId))->roleKey()->toString());
    }

    public function test_everyone_else_is_refused_every_request_operation(): void
    {
        $organizationId = $this->organization->id()->toString();

        foreach ($this->refusedOrganizationUsers() as $user => $who) {
            $this->assertRefused(MembershipAccessDeniedException::class, fn () => $this->listRequests()->execute(new ListPendingMembershipRequestsQuery($user, $organizationId)), "{$who}: list");
            $this->assertRefused(MembershipAccessDeniedException::class, fn () => $this->approveRequest()->execute(new ApproveMembershipRequestCommand($user, $this->requestId)), "{$who}: approve");
            $this->assertRefused(MembershipAccessDeniedException::class, fn () => $this->rejectRequest()->execute(new RejectMembershipRequestCommand($user, $this->requestId)), "{$who}: reject");
        }

        $this->assertSame(Membership::STATUS_REQUESTED, $this->requestStatus($this->requestId));
    }

    public function test_another_organizations_request_cannot_be_listed_approved_or_rejected(): void
    {
        $this->delegateHolds(DelegateArea::all());

        foreach ([1 => 'owner of A', 2 => 'MEMBERS delegate of A'] as $user => $who) {
            $this->assertRefused(MembershipAccessDeniedException::class, fn () => $this->listRequests()->execute(new ListPendingMembershipRequestsQuery($user, $this->otherOrganization->id()->toString())), "{$who}: list B");
            $this->assertRefused(MembershipAccessDeniedException::class, fn () => $this->approveRequest()->execute(new ApproveMembershipRequestCommand($user, $this->otherOrganizationRequestId)), "{$who}: approve B request");
            $this->assertRefused(MembershipAccessDeniedException::class, fn () => $this->rejectRequest()->execute(new RejectMembershipRequestCommand($user, $this->otherOrganizationRequestId)), "{$who}: reject B request");
        }

        $this->assertSame(Membership::STATUS_REQUESTED, $this->requestStatus($this->otherOrganizationRequestId));
        // A's own list never carries B's request.
        $listed = $this->listRequests()->execute(new ListPendingMembershipRequestsQuery(2, $this->organization->id()->toString()));
        $this->assertNotContains($this->otherOrganizationRequestId, array_map(static fn (MembershipRequestResult $request) => $request->id, $listed));
    }

    // ---- 公演側の参加コード -----------------------------------------------

    public function test_primary_manager_lists_issues_and_disables_production_join_keys(): void
    {
        $productionId = $this->production->id()->toString();

        $issued = $this->issueProductionKey()->execute(new IssueProductionJoinKeyCommand(4, $productionId));
        $listed = $this->listProductionKeys()->execute(new ListProductionJoinKeysQuery(4, $productionId));
        $this->assertSame([$issued->id], array_map(static fn (JoinKeyResult $key) => $key->id, $listed));
        $this->assertSame(JoinKey::TARGET_TYPE_PRODUCTION, $listed[0]->targetType);

        $this->assertSame(JoinKey::STATUS_DISABLED, $this->disableKey()->execute(new DisableJoinKeyCommand(4, $issued->id))->status);
    }

    public function test_organization_roles_and_areas_grant_nothing_on_a_production(): void
    {
        $this->delegateHolds(DelegateArea::all());
        $productionId = $this->production->id()->toString();
        $issued = $this->issueProductionKey()->execute(new IssueProductionJoinKeyCommand(4, $productionId));

        $refused = [
            1 => 'Organization Owner (not PrimaryManager)',
            2 => 'Organization delegate holding every area',
            3 => 'plain member',
            5 => 'Production 代理人 (PARTICIPANT_MANAGER + REHEARSAL_MANAGER)',
            9 => 'outsider',
            0 => 'signed out',
        ];
        foreach ($refused as $user => $who) {
            $this->assertRefused(ProductionAccessDeniedException::class, fn () => $this->listProductionKeys()->execute(new ListProductionJoinKeysQuery($user, $productionId)), "{$who}: list");
            $this->assertRefused(ProductionAccessDeniedException::class, fn () => $this->issueProductionKey()->execute(new IssueProductionJoinKeyCommand($user, $productionId)), "{$who}: issue");
            $this->assertRefused(JoinKeyAccessDeniedException::class, fn () => $this->disableKey()->execute(new DisableJoinKeyCommand($user, $issued->id)), "{$who}: disable");
        }

        $keys = $this->joinKeys->findByTarget(JoinKey::TARGET_TYPE_PRODUCTION, $productionId);
        $this->assertCount(1, $keys);
        $this->assertSame(JoinKey::STATUS_ACTIVE, array_values($keys)[0]->status());
    }

    public function test_a_primary_manager_gets_nothing_from_another_production(): void
    {
        $otherProductionId = $this->otherProduction->id()->toString();
        $otherKey = $this->issueProductionKey()->execute(new IssueProductionJoinKeyCommand(9, $otherProductionId));

        $this->assertRefused(ProductionAccessDeniedException::class, fn () => $this->listProductionKeys()->execute(new ListProductionJoinKeysQuery(4, $otherProductionId)), 'list Q');
        $this->assertRefused(ProductionAccessDeniedException::class, fn () => $this->issueProductionKey()->execute(new IssueProductionJoinKeyCommand(4, $otherProductionId)), 'issue Q');
        $this->assertRefused(JoinKeyAccessDeniedException::class, fn () => $this->disableKey()->execute(new DisableJoinKeyCommand(4, $otherKey->id)), 'disable Q key');

        // The Organization-side list never carries a Production's keys, and vice versa.
        $this->issueProductionKey()->execute(new IssueProductionJoinKeyCommand(4, $this->production->id()->toString()));
        $this->assertSame([], $this->listOrganizationKeys()->execute(new ListOrganizationJoinKeysQuery(1, $this->organization->id()->toString())));
    }
}
