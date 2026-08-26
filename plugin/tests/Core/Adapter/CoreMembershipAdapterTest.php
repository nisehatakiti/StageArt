<?php

declare(strict_types=1);

namespace StageArt\Tests\Core\Adapter;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
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

final class CoreMembershipAdapterTest extends TestCase
{
    private function adapter(
        InMemoryParticipantRepository $participants,
        InMemoryProductionRepository $productions,
        InMemoryPersonRepository $people,
        InMemoryProductionDelegateRepository $delegates,
        InMemoryOrganizationRepository $organizations,
        InMemoryMembershipRepository $memberships
    ): CoreMembershipAdapter {
        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);

        return new CoreMembershipAdapter($participants, $productions, $people, $productionAuthorization);
    }

    public function test_returns_only_active_person_subject_participants(): void
    {
        $participants = new InMemoryParticipantRepository();
        $productionId = ProductionId::generate();

        $activePerson = PersonId::generate();
        $active = Participant::create(
            $productionId,
            ParticipantSubjectType::person(),
            $activePerson->toString(),
            ParticipantType::cast()
        );
        $participants->save($active);

        $rejectedPerson = Participant::requestParticipation(
            $productionId,
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast()
        );
        $rejectedPerson->reject();
        $participants->save($rejectedPerson);

        $organizationSubject = Participant::create(
            $productionId,
            ParticipantSubjectType::organization(),
            'some-organization-id',
            ParticipantType::staff()
        );
        $participants->save($organizationSubject);

        $adapter = $this->adapter(
            $participants,
            new InMemoryProductionRepository(),
            new InMemoryPersonRepository(),
            new InMemoryProductionDelegateRepository(),
            new InMemoryOrganizationRepository(),
            new InMemoryMembershipRepository()
        );

        $result = $adapter->activeProductionMemberPersonIds($productionId);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->equals($activePerson));
    }

    public function test_returns_empty_array_when_production_has_no_participants(): void
    {
        $adapter = $this->adapter(
            new InMemoryParticipantRepository(),
            new InMemoryProductionRepository(),
            new InMemoryPersonRepository(),
            new InMemoryProductionDelegateRepository(),
            new InMemoryOrganizationRepository(),
            new InMemoryMembershipRepository()
        );

        $result = $adapter->activeProductionMemberPersonIds(ProductionId::generate());

        $this->assertSame([], $result);
    }

    public function test_is_production_member_true_for_primary_manager_delegate_and_active_participant(): void
    {
        $participants = new InMemoryParticipantRepository();
        $productions = new InMemoryProductionRepository();
        $people = new InMemoryPersonRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();

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

        $castPerson = Person::create(3);
        $people->save($castPerson);
        $participants->save(Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $castPerson->id()->toString(),
            ParticipantType::cast()
        ));

        $outsider = Person::create(4);
        $people->save($outsider);

        $adapter = $this->adapter($participants, $productions, $people, $delegates, $organizations, $memberships);

        $this->assertTrue($adapter->isProductionMember($primaryManager->id(), $production->id()));
        $this->assertTrue($adapter->isProductionMember($delegatePerson->id(), $production->id()));
        $this->assertTrue($adapter->isProductionMember($castPerson->id(), $production->id()));
        $this->assertFalse($adapter->isProductionMember($outsider->id(), $production->id()));
    }

    public function test_is_production_member_false_for_unresolvable_person_or_production(): void
    {
        $adapter = $this->adapter(
            new InMemoryParticipantRepository(),
            new InMemoryProductionRepository(),
            new InMemoryPersonRepository(),
            new InMemoryProductionDelegateRepository(),
            new InMemoryOrganizationRepository(),
            new InMemoryMembershipRepository()
        );

        $this->assertFalse($adapter->isProductionMember(PersonId::generate(), ProductionId::generate()));
    }
}
