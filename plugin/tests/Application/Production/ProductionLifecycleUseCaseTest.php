<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ActivateProductionCommand;
use StageArt\Application\Production\ActivateProductionUseCase;
use StageArt\Application\Production\ArchiveProductionCommand;
use StageArt\Application\Production\ArchiveProductionUseCase;
use StageArt\Application\Production\CancelProductionCommand;
use StageArt\Application\Production\CancelProductionUseCase;
use StageArt\Application\Production\CompleteProductionCommand;
use StageArt\Application\Production\CompleteProductionUseCase;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\StartProductionPlanningCommand;
use StageArt\Application\Production\StartProductionPlanningUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
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
 * Phase 6.1: covers the Production Lifecycle Action UseCases end to end -
 * the full DRAFT -> PLANNING -> ACTIVE -> COMPLETED -> ARCHIVED chain,
 * Cancel from a mid-chain state, invalid-transition rejection at the
 * Application layer (surfaced from the Domain Guard), unauthorized
 * rejection, and Production Scope isolation (PrimaryManager of a
 * different Production cannot act).
 */
final class ProductionLifecycleUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;

    private StartProductionPlanningUseCase $startPlanning;
    private ActivateProductionUseCase $activate;
    private CompleteProductionUseCase $complete;
    private ArchiveProductionUseCase $archive;
    private CancelProductionUseCase $cancel;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );

        $this->startPlanning = new StartProductionPlanningUseCase($this->productions, $productionAuthorization);
        $this->activate = new ActivateProductionUseCase($this->productions, $productionAuthorization);
        $this->complete = new CompleteProductionUseCase($this->productions, $productionAuthorization);
        $this->archive = new ArchiveProductionUseCase($this->productions, $productionAuthorization);
        $this->cancel = new CancelProductionUseCase($this->productions, $productionAuthorization);
    }

    /**
     * @return array{0: Production, 1: Person} Production, PrimaryManager
     */
    private function givenProduction(int $primaryManagerWordPressUserId): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return [$production, $primaryManager];
    }

    public function test_primary_manager_can_advance_through_the_full_lifecycle(): void
    {
        [$production] = $this->givenProduction(1);

        $result = $this->startPlanning->execute(new StartProductionPlanningCommand($production->id()->toString(), 1));
        $this->assertSame('PLANNING', $result->status);

        $result = $this->activate->execute(new ActivateProductionCommand($production->id()->toString(), 1));
        $this->assertSame('ACTIVE', $result->status);

        $result = $this->complete->execute(new CompleteProductionCommand($production->id()->toString(), 1));
        $this->assertSame('COMPLETED', $result->status);

        $result = $this->archive->execute(new ArchiveProductionCommand($production->id()->toString(), 1));
        $this->assertSame('ARCHIVED', $result->status);
    }

    public function test_primary_manager_can_cancel_from_active(): void
    {
        [$production] = $this->givenProduction(1);

        $this->startPlanning->execute(new StartProductionPlanningCommand($production->id()->toString(), 1));
        $this->activate->execute(new ActivateProductionCommand($production->id()->toString(), 1));
        $result = $this->cancel->execute(new CancelProductionCommand($production->id()->toString(), 1));

        $this->assertSame('CANCELLED', $result->status);
    }

    public function test_skipping_planning_to_activate_is_rejected(): void
    {
        [$production] = $this->givenProduction(1);

        $this->expectException(InvalidArgumentException::class);

        $this->activate->execute(new ActivateProductionCommand($production->id()->toString(), 1));
    }

    public function test_completing_a_draft_production_is_rejected(): void
    {
        [$production] = $this->givenProduction(1);

        $this->expectException(InvalidArgumentException::class);

        $this->complete->execute(new CompleteProductionCommand($production->id()->toString(), 1));
    }

    public function test_archiving_a_non_completed_production_is_rejected(): void
    {
        [$production] = $this->givenProduction(1);
        $this->startPlanning->execute(new StartProductionPlanningCommand($production->id()->toString(), 1));

        $this->expectException(InvalidArgumentException::class);

        $this->archive->execute(new ArchiveProductionCommand($production->id()->toString(), 1));
    }

    public function test_non_primary_manager_cannot_advance_the_lifecycle(): void
    {
        [$production] = $this->givenProduction(1);

        $member = Person::create(2);
        $this->people->save($member);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->startPlanning->execute(new StartProductionPlanningCommand($production->id()->toString(), 2));
    }

    public function test_primary_manager_of_a_different_production_cannot_advance_this_one(): void
    {
        [$productionA] = $this->givenProduction(1);
        [$productionB, ] = $this->givenProduction(2);

        $this->expectException(ProductionAccessDeniedException::class);

        // WordPress user 2 (PrimaryManager of Production B only) attempts
        // to advance Production A's Lifecycle.
        $this->startPlanning->execute(new StartProductionPlanningCommand($productionA->id()->toString(), 2));
    }
}
