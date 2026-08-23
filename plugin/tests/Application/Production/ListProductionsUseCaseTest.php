<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ListProductionsForPersonQuery;
use StageArt\Application\Production\ListProductionsUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;

final class ListProductionsUseCaseTest extends TestCase
{
    public function test_list_includes_primary_manager_and_delegate_productions_but_excludes_archived_and_unrelated(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $delegates,
            new InMemoryParticipantRepository()
        );
        $listProductions = new ListProductionsUseCase($productions, $delegates, $productionAuthorization);

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $person = Person::create(1);
        $people->save($person);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $person->id()));

        $project = Project::create($organization->id(), 'Season');

        // Production A: person is PrimaryManager, ACTIVE - should appear.
        $productionA = Production::create($project->id(), new ProductionName('Show A'), $person->id());
        $productions->save($productionA);

        // Production B: person is PrimaryManager, but ARCHIVED - should be
        // excluded. ARCHIVED is only reachable via Production Settlement in
        // the real Domain rule (Production::changeStatus() blocks a direct
        // COMPLETED -> ARCHIVED call), so this uses reconstitute() to build
        // an already-ARCHIVED instance directly, the same way the
        // Infrastructure hydrate() would for a persisted ARCHIVED row.
        $productionB = Production::reconstitute(
            ProductionId::generate(),
            $project->id(),
            new ProductionName('Show B'),
            null,
            ProductionStatus::fromString(ProductionStatus::ARCHIVED),
            $person->id(),
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
        $productions->save($productionB);

        // Production C: someone else is PrimaryManager, person has an
        // ACTIVE ProductionDelegate - should appear.
        $otherManager = Person::create(2);
        $people->save($otherManager);
        $productionC = Production::create($project->id(), new ProductionName('Show C'), $otherManager->id());
        $productions->save($productionC);
        $delegates->save(ProductionDelegate::create(
            $productionC->id(),
            $person->id(),
            RoleKey::participantManager(),
            $otherManager->id()
        ));

        // Production D: person has no relationship at all - should not appear.
        $productionD = Production::create($project->id(), new ProductionName('Show D'), $otherManager->id());
        $productions->save($productionD);

        $results = $listProductions->execute(new ListProductionsForPersonQuery(1));
        $resultIds = array_map(static fn ($result) => $result->id, $results);

        $this->assertContains($productionA->id()->toString(), $resultIds);
        $this->assertContains($productionC->id()->toString(), $resultIds);
        $this->assertNotContains($productionB->id()->toString(), $resultIds);
        $this->assertNotContains($productionD->id()->toString(), $resultIds);
    }
}
