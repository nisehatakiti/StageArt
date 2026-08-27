<?php

declare(strict_types=1);

namespace StageArt\Tests\Core\Adapter;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Contract\OrganizationCapability;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
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
 * Verifies the AuthorizationContract's ID-based surface behaves
 * identically to the underlying (unchanged) ProductionAuthorizationService
 * logic it wraps - the generic `canForProduction()` replacing the
 * removed `canManageRehearsals()`/`canManageAccounting()` Module-named
 * methods must not silently change who can do what.
 */
final class CoreAuthorizationAdapterTest extends TestCase
{
    public function test_primary_manager_has_every_capability(): void
    {
        $people = new InMemoryPersonRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $primaryManager = Person::create(1);
        $people->save($primaryManager);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $productions->save($production);

        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);
        $adapter = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);

        $this->assertTrue($adapter->canForProduction($primaryManager->id(), $production->id(), RehearsalCapability::MANAGE));
        $this->assertTrue($adapter->canForProduction($primaryManager->id(), $production->id(), 'AnyOther.Capability'));
    }

    public function test_delegate_without_the_role_lacks_the_capability(): void
    {
        $people = new InMemoryPersonRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $primaryManager = Person::create(1);
        $people->save($primaryManager);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $productions->save($production);

        $outsider = Person::create(2);
        $people->save($outsider);

        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);
        $adapter = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);

        $this->assertFalse($adapter->canForProduction($outsider->id(), $production->id(), RehearsalCapability::MANAGE));
    }

    public function test_delegate_with_rehearsal_manager_role_has_rehearsal_capability_only(): void
    {
        $people = new InMemoryPersonRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $primaryManager = Person::create(1);
        $people->save($primaryManager);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $productions->save($production);

        $delegatePerson = Person::create(2);
        $people->save($delegatePerson);
        $delegates->save(ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::rehearsalManager(),
            $primaryManager->id()
        ));

        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);
        $adapter = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);

        $this->assertTrue($adapter->canForProduction($delegatePerson->id(), $production->id(), RehearsalCapability::MANAGE));
        // No ACCOUNTING_MANAGER Role/Permission entry exists yet - the same
        // capability-string-based check correctly evaluates to false for a
        // capability no Role grants, without Core needing to know
        // "Accounting" exists.
        $this->assertFalse($adapter->canForProduction($delegatePerson->id(), $production->id(), 'Accounting.Update'));
    }

    public function test_unresolvable_person_or_production_is_false(): void
    {
        $people = new InMemoryPersonRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();

        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);
        $adapter = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);

        $this->assertFalse($adapter->canForProduction(PersonId::generate(), ProductionId::generate(), RehearsalCapability::MANAGE));
    }

    public function test_organization_owner_has_the_owner_capability(): void
    {
        $people = new InMemoryPersonRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $owner->id()));

        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);
        $adapter = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);

        $this->assertTrue($adapter->canForOrganization($owner->id(), $organization->id(), OrganizationCapability::OWNER));
    }

    public function test_organization_member_lacks_the_owner_capability(): void
    {
        $people = new InMemoryPersonRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $owner->id()));

        $member = Person::create(2);
        $people->save($member);
        $memberships->save(Membership::create($organization->id(), $member->id(), RoleKey::member()));

        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);
        $adapter = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);

        $this->assertFalse($adapter->canForOrganization($member->id(), $organization->id(), OrganizationCapability::OWNER));
    }

    public function test_unresolvable_person_lacks_any_organization_capability(): void
    {
        $people = new InMemoryPersonRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);
        $adapter = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);

        $this->assertFalse($adapter->canForOrganization(PersonId::generate(), $organization->id(), OrganizationCapability::OWNER));
    }

    public function test_unrecognized_organization_capability_is_false_even_for_the_owner(): void
    {
        $people = new InMemoryPersonRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $owner->id()));

        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);
        $adapter = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);

        $this->assertFalse($adapter->canForOrganization($owner->id(), $organization->id(), 'Some.Unrecognized.Capability'));
    }
}
