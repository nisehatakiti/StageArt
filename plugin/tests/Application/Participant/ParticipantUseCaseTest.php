<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Participant;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Participant\CancelParticipantCommand;
use StageArt\Application\Participant\CancelParticipantUseCase;
use StageArt\Application\Participant\CreateParticipantCommand;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\Participant\GetParticipantQuery;
use StageArt\Application\Participant\GetParticipantUseCase;
use StageArt\Application\Participant\ListParticipantsQuery;
use StageArt\Application\Participant\ListParticipantsUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Application\Participant\ParticipantSubjectNotEligibleException;
use StageArt\Application\Participant\UpdateParticipantCommand;
use StageArt\Application\Participant\UpdateParticipantUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class ParticipantUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private CreateParticipantUseCase $createParticipant;
    private GetParticipantUseCase $getParticipant;
    private ListParticipantsUseCase $listParticipants;
    private UpdateParticipantUseCase $updateParticipant;
    private CancelParticipantUseCase $cancelParticipant;

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

        $this->createParticipant = new CreateParticipantUseCase(
            $this->productions,
            $this->participants,
            $this->people,
            $this->organizations,
            $productionAuthorization,
            new InMemoryTransactionManager()
        );
        $this->getParticipant = new GetParticipantUseCase($this->participants, $this->productions, $productionAuthorization);
        $this->listParticipants = new ListParticipantsUseCase($this->participants, $this->productions, $productionAuthorization);
        $this->updateParticipant = new UpdateParticipantUseCase($this->participants, $this->productions, $productionAuthorization);
        $this->cancelParticipant = new CancelParticipantUseCase($this->participants, $this->productions, $productionAuthorization);
    }

    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId): Production
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

    public function test_primary_manager_can_register_a_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $castPerson = Person::create(2);
        $this->people->save($castPerson);

        $result = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));

        $this->assertSame('ACTIVE', $result->status);
        $this->assertSame('CAST', $result->participantType);
    }

    public function test_delegate_with_participant_manager_role_can_register_a_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $delegatePerson = Person::create(2);
        $this->people->save($delegatePerson);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        ));

        $castPerson = Person::create(3);
        $this->people->save($castPerson);

        $result = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            2,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));

        $this->assertSame('CAST', $result->participantType);
    }

    public function test_inactive_delegate_cannot_register_a_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

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

        $castPerson = Person::create(3);
        $this->people->save($castPerson);

        $this->expectException(ParticipantAccessDeniedException::class);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            2,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));
    }

    public function test_unrelated_person_cannot_register_a_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $outsider = Person::create(2);
        $this->people->save($outsider);

        $castPerson = Person::create(3);
        $this->people->save($castPerson);

        $this->expectException(ParticipantAccessDeniedException::class);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            2,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));
    }

    public function test_subject_person_must_exist(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $this->expectException(ParticipantSubjectNotEligibleException::class);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            PersonId::generate()->toString(),
            'CAST'
        ));
    }

    public function test_duplicate_subject_and_type_registration_is_rejected(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $castPerson = Person::create(2);
        $this->people->save($castPerson);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));

        $this->expectException(ParticipantAlreadyExistsException::class);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $castPerson->id()->toString(),
            'CAST'
        ));
    }

    public function test_same_subject_with_a_different_participant_type_is_allowed(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $person = Person::create(2);
        $this->people->save($person);

        $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));

        $second = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'STAFF'
        ));

        $this->assertSame('STAFF', $second->participantType);
        $this->assertCount(2, $this->listParticipants->execute(new ListParticipantsQuery($production->id()->toString(), 1)));
    }

    public function test_get_list_update_and_cancel(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $person = Person::create(2);
        $this->people->save($person);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $production->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));

        $fetched = $this->getParticipant->execute(new GetParticipantQuery($created->id, 1));
        $this->assertSame($created->id, $fetched->id);

        $updated = $this->updateParticipant->execute(new UpdateParticipantCommand($created->id, 1, 'STAFF', 'INACTIVE'));
        $this->assertSame('STAFF', $updated->participantType);
        $this->assertSame('INACTIVE', $updated->status);

        $this->cancelParticipant->execute(new CancelParticipantCommand($created->id, 1));

        // Cancel is a Status change, not a physical delete: the record is
        // still retrievable, per Participant.md's "原則として物理削除しない".
        $cancelled = $this->getParticipant->execute(new GetParticipantQuery($created->id, 1));
        $this->assertSame('CANCELLED', $cancelled->status);
    }

    public function test_participant_of_production_a_is_not_accessible_from_production_b(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $this->givenProductionWithPrimaryManager(2);

        $person = Person::create(3);
        $this->people->save($person);

        $created = $this->createParticipant->execute(new CreateParticipantCommand(
            $productionA->id()->toString(),
            1,
            'PERSON',
            $person->id()->toString(),
            'CAST'
        ));

        $this->expectException(ParticipantAccessDeniedException::class);

        // WordPress user 2 is PrimaryManager of Production B only.
        $this->getParticipant->execute(new GetParticipantQuery($created->id, 2));
    }
}
