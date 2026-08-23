<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\GetProductionQuery;
use StageArt\Application\Production\GetProductionUseCase;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\UpdateProductionCommand;
use StageArt\Application\Production\UpdateProductionUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;

/**
 * Verifies ProductionAuthorizationService's literal reading of
 * Authorization.md's Decision Flow for Production Scope: PrimaryManager
 * or an ACTIVE ProductionDelegate only - no fallback to Organization
 * Membership/Owner, and no cross-Production leakage. Mirrors
 * OrganizationAuthorizationServiceTest's "Organization A cannot read
 * Organization B" shape, applied to Production Scope.
 */
final class ProductionAuthorizationTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private GetProductionUseCase $getProduction;
    private UpdateProductionUseCase $updateProduction;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            new InMemoryParticipantRepository()
        );

        $this->getProduction = new GetProductionUseCase($this->productions, $productionAuthorization);
        $this->updateProduction = new UpdateProductionUseCase($this->productions, $productionAuthorization);
    }

    private function givenProduction(int $primaryManagerWordPressUserId): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return $production;
    }

    public function test_primary_manager_can_read_and_update_their_production(): void
    {
        $production = $this->givenProduction(1);

        $result = $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 1));
        $this->assertTrue($result->isPrimaryManager);

        $updated = $this->updateProduction->execute(new UpdateProductionCommand(
            $production->id()->toString(),
            1,
            'Renamed Show',
            '旗揚げ公演'
        ));
        $this->assertSame('Renamed Show', $updated->name);
        $this->assertSame('旗揚げ公演', $updated->titleHeading);
    }

    public function test_organization_owner_without_primary_manager_or_delegate_status_cannot_read_production(): void
    {
        $production = $this->givenProduction(1);

        // A second Person exists but is neither PrimaryManager nor an
        // active ProductionDelegate on this specific Production - per
        // Authorization.md's Decision Flow, Production Scope has no
        // fallback to Organization Membership/Owner.
        $otherPerson = Person::create(2);
        $this->people->save($otherPerson);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 2));
    }

    public function test_active_delegate_can_read_but_not_update_the_production(): void
    {
        $production = $this->givenProduction(1);

        $delegatePerson = Person::create(2);
        $this->people->save($delegatePerson);
        $delegate = ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        );
        $this->delegates->save($delegate);

        $result = $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 2));
        $this->assertFalse($result->isPrimaryManager);
        $this->assertSame(RoleKey::PARTICIPANT_MANAGER, $result->delegateRole);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->updateProduction->execute(new UpdateProductionCommand(
            $production->id()->toString(),
            2,
            'Hijacked Name'
        ));
    }

    public function test_inactive_delegate_cannot_read_the_production(): void
    {
        $production = $this->givenProduction(1);

        $delegatePerson = Person::create(2);
        $this->people->save($delegatePerson);
        $delegate = ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        );
        $delegate->deactivate($production->primaryManagerPersonId());
        $this->delegates->save($delegate);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 2));
    }

    public function test_primary_manager_of_production_a_cannot_access_production_b(): void
    {
        $productionA = $this->givenProduction(1);
        $productionB = $this->givenProduction(2);

        $this->expectException(ProductionAccessDeniedException::class);

        // WordPress user 1 (PrimaryManager of Production A only) attempts
        // to read Production B.
        $this->getProduction->execute(new GetProductionQuery($productionB->id()->toString(), 1));
    }

    public function test_being_a_member_with_no_person_record_is_rejected(): void
    {
        $production = $this->givenProduction(1);

        $this->expectException(ProductionAccessDeniedException::class);

        // WordPress user 999 has never touched StageArt: no Person at all.
        $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 999));
    }
}
