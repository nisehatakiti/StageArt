<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\GetProductionOverviewQuery;
use StageArt\Application\Production\GetProductionOverviewUseCase;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
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
 * 参加者向け「公演概要ダッシュボード」instruction: GetProductionOverviewUseCase
 * is gated by isProductionMember() (PrimaryManager ∪ active Delegate ∪
 * active Person-Participant), unlike GetProductionUseCase's
 * canReadProduction() (PrimaryManager ∪ active Delegate only - see
 * ProductionAuthorizationTest's own
 * test_organization_owner_without_primary_manager_or_delegate_status_cannot_read_production,
 * which is exactly the population this Use Case newly admits).
 */
final class GetProductionOverviewUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private GetProductionOverviewUseCase $getProductionOverview;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $authorization = new ProductionAuthorizationService($organizationAuthorization, $this->delegates, $this->participants);

        $this->getProductionOverview = new GetProductionOverviewUseCase($this->productions, $authorization);
    }

    private function givenProduction(int $primaryManagerWordPressUserId): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('踊れチュパカブラ'), $primaryManager->id());
        $this->productions->save($production);

        return $production;
    }

    public function test_primary_manager_can_read_the_overview(): void
    {
        $production = $this->givenProduction(1);

        $result = $this->getProductionOverview->execute(new GetProductionOverviewQuery($production->id()->toString(), 1));

        $this->assertSame('踊れチュパカブラ', $result->name);
        $this->assertTrue($result->isPrimaryManager);
    }

    public function test_active_delegate_can_read_the_overview(): void
    {
        $production = $this->givenProduction(1);

        $delegatePerson = Person::create(2);
        $this->people->save($delegatePerson);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        ));

        $result = $this->getProductionOverview->execute(new GetProductionOverviewQuery($production->id()->toString(), 2));

        $this->assertSame('踊れチュパカブラ', $result->name);
        $this->assertFalse($result->isPrimaryManager);
    }

    /**
     * The key new population this Use Case admits: an ordinary ACTIVE
     * PERSON Participant who is neither PrimaryManager nor a Delegate -
     * GetProductionUseCase's canReadProduction() would 403 this exact
     * Person (see ProductionAuthorizationTest's
     * test_organization_owner_without_primary_manager_or_delegate_status_cannot_read_production).
     */
    public function test_an_active_person_participant_with_no_delegate_role_can_read_the_overview(): void
    {
        $production = $this->givenProduction(1);

        $castPerson = Person::create(2);
        $this->people->save($castPerson);
        $this->participants->save(Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $castPerson->id()->toString(),
            ParticipantType::cast()
        ));

        $result = $this->getProductionOverview->execute(new GetProductionOverviewQuery($production->id()->toString(), 2));

        $this->assertSame('踊れチュパカブラ', $result->name);
        $this->assertFalse($result->isPrimaryManager);
        $this->assertNull($result->delegateRole);
    }

    public function test_a_cancelled_participant_cannot_read_the_overview(): void
    {
        $production = $this->givenProduction(1);

        $castPerson = Person::create(2);
        $this->people->save($castPerson);
        $participant = Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $castPerson->id()->toString(),
            ParticipantType::cast()
        );
        $participant->changeStatus(ParticipantStatus::fromString(ParticipantStatus::CANCELLED));
        $this->participants->save($participant);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->getProductionOverview->execute(new GetProductionOverviewQuery($production->id()->toString(), 2));
    }

    public function test_an_unrelated_person_cannot_read_the_overview(): void
    {
        $production = $this->givenProduction(1);

        $outsider = Person::create(2);
        $this->people->save($outsider);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->getProductionOverview->execute(new GetProductionOverviewQuery($production->id()->toString(), 2));
    }

    public function test_a_wordpress_user_with_no_stageart_person_cannot_read_the_overview(): void
    {
        $production = $this->givenProduction(1);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->getProductionOverview->execute(new GetProductionOverviewQuery($production->id()->toString(), 999));
    }
}
