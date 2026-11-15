<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Participant;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Participant\ListMyParticipatingProductionsQuery;
use StageArt\Application\Participant\ListMyParticipatingProductionsUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;

/**
 * 「参加している公演・活動」の正式なデータソース確認: the caller's own
 * ACTIVE PERSON Participant rows, independent of Rehearsal/Attendance
 * data (see ListMyParticipatingProductionsUseCase's own docblock for why
 * this deliberately diverges from GetMyDashboardUseCase's
 * upcoming_rehearsals, which is attendance-based and was previously
 * (mis)used by the mobile Client as a "participation list" proxy).
 */
final class ListMyParticipatingProductionsUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private ListMyParticipatingProductionsUseCase $listMyParticipatingProductions;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );

        $this->listMyParticipatingProductions = new ListMyParticipatingProductionsUseCase(
            $this->participants,
            $this->productions,
            $productionAuthorization
        );
    }

    private function givenProduction(string $name): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $manager = Person::create(999);
        $this->people->save($manager);

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName($name), $manager->id());
        $this->productions->save($production);

        return $production;
    }

    public function test_a_person_with_an_active_person_participant_sees_that_production(): void
    {
        $production = $this->givenProduction('踊れチュパカブラ');

        $person = Person::create(1);
        $this->people->save($person);
        $this->participants->save(Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::cast()
        ));

        $results = $this->listMyParticipatingProductions->execute(new ListMyParticipatingProductionsQuery(1));

        $this->assertCount(1, $results);
        $this->assertSame($production->id()->toString(), $results[0]->productionId);
        $this->assertSame('踊れチュパカブラ', $results[0]->productionName);
        $this->assertSame('CAST', $results[0]->participantType);
    }

    public function test_a_production_with_no_upcoming_rehearsal_still_appears_when_the_participant_is_active(): void
    {
        // Deliberately creates no Rehearsal/Attendance data at all - this
        // UseCase never queries it, unlike the old upcoming_rehearsals
        // proxy the mobile Client used to rely on.
        $production = $this->givenProduction('稽古予定のない公演');

        $person = Person::create(1);
        $this->people->save($person);
        $this->participants->save(Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::staff()
        ));

        $results = $this->listMyParticipatingProductions->execute(new ListMyParticipatingProductionsQuery(1));

        $this->assertCount(1, $results);
        $this->assertSame($production->id()->toString(), $results[0]->productionId);
    }

    public function test_a_person_with_no_participant_record_sees_an_empty_list(): void
    {
        $person = Person::create(1);
        $this->people->save($person);

        $results = $this->listMyParticipatingProductions->execute(new ListMyParticipatingProductionsQuery(1));

        $this->assertSame([], $results);
    }

    /**
     * @dataProvider notParticipatingStatuses
     */
    public function test_a_non_active_participant_status_is_excluded(string $status): void
    {
        $production = $this->givenProduction('Show');

        $person = Person::create(1);
        $this->people->save($person);
        $participant = Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::cast()
        );
        $participant->changeStatus(ParticipantStatus::fromString($status));
        $this->participants->save($participant);

        $results = $this->listMyParticipatingProductions->execute(new ListMyParticipatingProductionsQuery(1));

        $this->assertSame([], $results);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notParticipatingStatuses(): array
    {
        return [
            'CANCELLED' => ['CANCELLED'],
            'INACTIVE' => ['INACTIVE'],
            'DRAFT' => ['DRAFT'],
            'PENDING' => ['PENDING'],
            'REJECTED' => ['REJECTED'],
        ];
    }

    public function test_a_name_only_participant_never_appears_in_the_logged_in_persons_own_list(): void
    {
        $production = $this->givenProduction('Show');

        $person = Person::create(1);
        $this->people->save($person);

        // A NAME_ONLY Participant is not linked to any logged-in Person
        // (see ParticipantSubjectType::NAME_ONLY) - it must never be
        // findable via findBySubject(PERSON, ...) for this Person's id,
        // even if ids happened to collide (they do not in practice).
        $this->participants->save(Participant::createNameOnly(
            $production->id(),
            '山田太郎',
            ParticipantType::cast()
        ));

        $results = $this->listMyParticipatingProductions->execute(new ListMyParticipatingProductionsQuery(1));

        $this->assertSame([], $results);
    }

    public function test_only_the_callers_own_participations_are_returned_not_another_persons(): void
    {
        $productionA = $this->givenProduction('A');
        $productionB = $this->givenProduction('B');

        $personOne = Person::create(1);
        $this->people->save($personOne);
        $this->participants->save(Participant::create(
            $productionA->id(),
            ParticipantSubjectType::person(),
            $personOne->id()->toString(),
            ParticipantType::cast()
        ));

        $personTwo = Person::create(2);
        $this->people->save($personTwo);
        $this->participants->save(Participant::create(
            $productionB->id(),
            ParticipantSubjectType::person(),
            $personTwo->id()->toString(),
            ParticipantType::staff()
        ));

        $results = $this->listMyParticipatingProductions->execute(new ListMyParticipatingProductionsQuery(1));

        $this->assertCount(1, $results);
        $this->assertSame($productionA->id()->toString(), $results[0]->productionId);
    }

    public function test_requires_a_stageart_person_linked_to_the_wordpress_user(): void
    {
        $this->expectException(ParticipantAccessDeniedException::class);

        $this->listMyParticipatingProductions->execute(new ListMyParticipatingProductionsQuery(404));
    }
}
