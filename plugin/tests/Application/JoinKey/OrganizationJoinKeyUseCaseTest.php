<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\JoinKey;

use PHPUnit\Framework\TestCase;
use StageArt\Application\JoinKey\DisableJoinKeyCommand;
use StageArt\Application\JoinKey\DisableJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyCommand;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyUseCase;
use StageArt\Application\JoinKey\JoinKeyAccessDeniedException;
use StageArt\Application\JoinKey\JoinKeyNotFoundException;
use StageArt\Application\JoinKey\ResolveJoinKeyQuery;
use StageArt\Application\JoinKey\ResolveJoinKeyUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryJoinKeyRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;

final class OrganizationJoinKeyUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryJoinKeyRepository $joinKeys;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private OrganizationAuthorizationService $authorization;
    private Organization $organization;
    private Person $owner;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->joinKeys = new InMemoryJoinKeyRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);

        $this->organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($this->organization);

        $this->owner = Person::create(1);
        $this->people->save($this->owner);
        $this->memberships->save(Membership::createOwnerMembership($this->organization->id(), $this->owner->id()));
    }

    public function test_owner_can_issue_a_join_key(): void
    {
        $useCase = new IssueOrganizationJoinKeyUseCase($this->organizations, $this->joinKeys, $this->authorization);

        $result = $useCase->execute(new IssueOrganizationJoinKeyCommand(1, $this->organization->id()->toString()));

        $this->assertSame(JoinKey::TARGET_TYPE_ORGANIZATION, $result->targetType);
        $this->assertSame(8, strlen($result->code));
    }

    public function test_non_owner_cannot_issue_a_join_key(): void
    {
        $member = Person::create(2);
        $this->people->save($member);

        $useCase = new IssueOrganizationJoinKeyUseCase($this->organizations, $this->joinKeys, $this->authorization);

        $this->expectException(OrganizationAccessDeniedException::class);

        $useCase->execute(new IssueOrganizationJoinKeyCommand(2, $this->organization->id()->toString()));
    }

    public function test_resolve_returns_the_target_organization_name(): void
    {
        $issued = (new IssueOrganizationJoinKeyUseCase($this->organizations, $this->joinKeys, $this->authorization))->execute(
            new IssueOrganizationJoinKeyCommand(1, $this->organization->id()->toString())
        );

        $resolveUseCase = new ResolveJoinKeyUseCase($this->joinKeys, $this->organizations, new InMemoryProductionRepository());
        $result = $resolveUseCase->execute(new ResolveJoinKeyQuery($issued->code));

        $this->assertSame('Theatre Co', $result->targetName);
        $this->assertSame(JoinKey::TARGET_TYPE_ORGANIZATION, $result->targetType);
    }

    public function test_resolve_normalizes_hyphens_and_case(): void
    {
        $issued = (new IssueOrganizationJoinKeyUseCase($this->organizations, $this->joinKeys, $this->authorization))->execute(
            new IssueOrganizationJoinKeyCommand(1, $this->organization->id()->toString())
        );

        $lowercaseWithHyphens = strtolower(substr($issued->code, 0, 4) . '-' . substr($issued->code, 4));

        $resolveUseCase = new ResolveJoinKeyUseCase($this->joinKeys, $this->organizations, new InMemoryProductionRepository());
        $result = $resolveUseCase->execute(new ResolveJoinKeyQuery($lowercaseWithHyphens));

        $this->assertSame('Theatre Co', $result->targetName);
    }

    public function test_resolving_a_disabled_key_throws_not_found(): void
    {
        $issued = (new IssueOrganizationJoinKeyUseCase($this->organizations, $this->joinKeys, $this->authorization))->execute(
            new IssueOrganizationJoinKeyCommand(1, $this->organization->id()->toString())
        );

        $productionAuthorization = new ProductionAuthorizationService(
            $this->authorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $disableUseCase = new DisableJoinKeyUseCase($this->joinKeys, new InMemoryProductionRepository(), $this->authorization, $productionAuthorization);
        $disableUseCase->execute(new DisableJoinKeyCommand(1, $issued->id));

        $resolveUseCase = new ResolveJoinKeyUseCase($this->joinKeys, $this->organizations, new InMemoryProductionRepository());

        $this->expectException(JoinKeyNotFoundException::class);

        $resolveUseCase->execute(new ResolveJoinKeyQuery($issued->code));
    }

    public function test_non_owner_cannot_disable_the_join_key(): void
    {
        $issued = (new IssueOrganizationJoinKeyUseCase($this->organizations, $this->joinKeys, $this->authorization))->execute(
            new IssueOrganizationJoinKeyCommand(1, $this->organization->id()->toString())
        );

        $productionAuthorization = new ProductionAuthorizationService(
            $this->authorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $disableUseCase = new DisableJoinKeyUseCase($this->joinKeys, new InMemoryProductionRepository(), $this->authorization, $productionAuthorization);

        $this->expectException(JoinKeyAccessDeniedException::class);

        $disableUseCase->execute(new DisableJoinKeyCommand(999, $issued->id));
    }
}
