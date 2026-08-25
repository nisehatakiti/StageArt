<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Participant;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Participant\ApproveParticipantRequestCommand;
use StageArt\Application\Participant\ApproveParticipantRequestUseCase;
use StageArt\Application\Participant\ListPendingParticipantRequestsQuery;
use StageArt\Application\Participant\ListPendingParticipantRequestsUseCase;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Application\Participant\RejectParticipantRequestCommand;
use StageArt\Application\Participant\RejectParticipantRequestUseCase;
use StageArt\Application\Participant\RequestProductionParticipationCommand;
use StageArt\Application\Participant\RequestProductionParticipationUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryJoinKeyRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;

final class RequestProductionParticipationUseCaseTest extends TestCase
{
    private InMemoryProductionRepository $productions;
    private InMemoryParticipantRepository $participants;
    private InMemoryJoinKeyRepository $joinKeys;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private ProductionAuthorizationService $authorization;
    private Production $production;
    private Person $primaryManager;

    protected function setUp(): void
    {
        $this->productions = new InMemoryProductionRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->joinKeys = new InMemoryJoinKeyRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->authorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            new InMemoryProductionDelegateRepository(),
            $this->participants
        );

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        (new InMemoryOrganizationRepository())->save($organization);

        $this->primaryManager = Person::create(1);
        $this->people->save($this->primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $this->primaryManager->id()));

        $project = Project::create($organization->id(), null);

        $this->production = Production::create(
            $project->id(),
            new ProductionName('Autumn Play'),
            $this->primaryManager->id(),
            null,
            new ProductionSlug('autumn-play')
        );
        $this->production->publish();
        $this->productions->save($this->production);
    }

    public function test_requesting_via_a_published_production_id_creates_a_pending_participant(): void
    {
        $requester = Person::create(2);
        $this->people->save($requester);

        $useCase = new RequestProductionParticipationUseCase($this->productions, $this->participants, $this->joinKeys, $this->authorization);

        $result = $useCase->execute(new RequestProductionParticipationCommand(2, $this->production->id()->toString(), null, ParticipantType::CAST));

        $this->assertSame(ParticipantStatus::PENDING, $result->status);
        $this->assertSame(ParticipantType::CAST, $result->participantType);
    }

    public function test_requesting_via_an_unpublished_production_id_is_not_found(): void
    {
        $draftProduction = Production::create(
            Project::create((Organization::create(new OrganizationName('X')))->id(), null)->id(),
            new ProductionName('Draft Show'),
            $this->primaryManager->id()
        );
        $this->productions->save($draftProduction);

        $requester = Person::create(2);
        $this->people->save($requester);

        $useCase = new RequestProductionParticipationUseCase($this->productions, $this->participants, $this->joinKeys, $this->authorization);

        $this->expectException(ProductionNotFoundException::class);

        $useCase->execute(new RequestProductionParticipationCommand(2, $draftProduction->id()->toString(), null, ParticipantType::CAST));
    }

    public function test_requesting_twice_for_the_same_role_while_pending_is_rejected(): void
    {
        $requester = Person::create(2);
        $this->people->save($requester);

        $useCase = new RequestProductionParticipationUseCase($this->productions, $this->participants, $this->joinKeys, $this->authorization);
        $useCase->execute(new RequestProductionParticipationCommand(2, $this->production->id()->toString(), null, ParticipantType::CAST));

        $this->expectException(ParticipantAlreadyExistsException::class);

        $useCase->execute(new RequestProductionParticipationCommand(2, $this->production->id()->toString(), null, ParticipantType::CAST));
    }

    public function test_primary_manager_can_approve_and_reject_pending_requests(): void
    {
        $requester = Person::create(2);
        $this->people->save($requester);
        $rejectee = Person::create(3);
        $this->people->save($rejectee);

        $requestUseCase = new RequestProductionParticipationUseCase($this->productions, $this->participants, $this->joinKeys, $this->authorization);
        $toApprove = $requestUseCase->execute(new RequestProductionParticipationCommand(2, $this->production->id()->toString(), null, ParticipantType::CAST));
        $toReject = $requestUseCase->execute(new RequestProductionParticipationCommand(3, $this->production->id()->toString(), null, ParticipantType::STAFF));

        $listUseCase = new ListPendingParticipantRequestsUseCase($this->participants, $this->productions, $this->people, $this->authorization);
        $pending = $listUseCase->execute(new ListPendingParticipantRequestsQuery(1, $this->production->id()->toString()));
        $this->assertCount(2, $pending);

        $approveUseCase = new ApproveParticipantRequestUseCase($this->participants, $this->productions, $this->people, $this->authorization);
        $approved = $approveUseCase->execute(new ApproveParticipantRequestCommand(1, $toApprove->id));
        $this->assertSame(ParticipantStatus::ACTIVE, $approved->status);

        $rejectUseCase = new RejectParticipantRequestUseCase($this->participants, $this->productions, $this->people, $this->authorization);
        $rejected = $rejectUseCase->execute(new RejectParticipantRequestCommand(1, $toReject->id));
        $this->assertSame(ParticipantStatus::REJECTED, $rejected->status);

        $remaining = $listUseCase->execute(new ListPendingParticipantRequestsQuery(1, $this->production->id()->toString()));
        $this->assertCount(0, $remaining);
    }

    public function test_requesting_via_a_production_join_key_consumes_a_use(): void
    {
        $requester = Person::create(2);
        $this->people->save($requester);

        $joinKey = JoinKey::issueForProduction($this->production->id()->toString(), $this->primaryManager->id());
        $this->joinKeys->save($joinKey);

        $useCase = new RequestProductionParticipationUseCase($this->productions, $this->participants, $this->joinKeys, $this->authorization);
        $useCase->execute(new RequestProductionParticipationCommand(2, null, $joinKey->code(), ParticipantType::CAST));

        $this->assertSame(1, $this->joinKeys->findByCode($joinKey->code())->useCount());
    }
}
