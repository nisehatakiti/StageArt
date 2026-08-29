<?php

declare(strict_types=1);

namespace StageArt\Tests\Core\Adapter;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Project\ProjectId;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class CoreProductionContextAdapterTest extends TestCase
{
    public function test_returns_a_summary_for_an_existing_production(): void
    {
        $productions = new InMemoryProductionRepository();
        $production = Production::create(ProjectId::generate(), new ProductionName('Autumn Show'), PersonId::generate());
        $productions->save($production);

        $adapter = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));

        $summary = $adapter->getProduction($production->id());

        $this->assertNotNull($summary);
        $this->assertSame('Autumn Show', $summary->name);
        $this->assertTrue($summary->id->equals($production->id()));
        $this->assertSame('DRAFT', $summary->status);
    }

    public function test_returns_null_for_a_nonexistent_production(): void
    {
        $adapter = new CoreProductionContextAdapter(
            new InMemoryProductionRepository(),
            new ProductionOrganizationResolver(new InMemoryProjectRepository())
        );

        $this->assertNull($adapter->getProduction(ProductionId::generate()));
    }

    public function test_bulk_resolves_multiple_productions_by_id(): void
    {
        $productions = new InMemoryProductionRepository();
        $a = Production::create(ProjectId::generate(), new ProductionName('Show A'), PersonId::generate());
        $b = Production::create(ProjectId::generate(), new ProductionName('Show B'), PersonId::generate());
        $productions->save($a);
        $productions->save($b);

        $adapter = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));

        $summaries = $adapter->getProductions([$a->id(), $b->id(), ProductionId::generate()]);

        $this->assertCount(2, $summaries);
        $this->assertSame('Show A', $summaries[$a->id()->toString()]->name);
        $this->assertSame('Show B', $summaries[$b->id()->toString()]->name);
    }

    public function test_bulk_resolve_returns_empty_array_for_empty_input(): void
    {
        $adapter = new CoreProductionContextAdapter(
            new InMemoryProductionRepository(),
            new ProductionOrganizationResolver(new InMemoryProjectRepository())
        );

        $this->assertSame([], $adapter->getProductions([]));
    }

    public function test_resolves_the_owning_organization_id(): void
    {
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $project = Project::create($organization->id(), 'Season');
        $projects->save($project);

        $production = Production::create($project->id(), new ProductionName('Autumn Show'), PersonId::generate());
        $productions->save($production);

        $adapter = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver($projects));

        $organizationId = $adapter->getProductionOrganizationId($production->id());

        $this->assertNotNull($organizationId);
        $this->assertTrue($organizationId->equals($organization->id()));
    }

    public function test_organization_id_is_null_when_the_production_does_not_exist(): void
    {
        $adapter = new CoreProductionContextAdapter(
            new InMemoryProductionRepository(),
            new ProductionOrganizationResolver(new InMemoryProjectRepository())
        );

        $this->assertNull($adapter->getProductionOrganizationId(ProductionId::generate()));
    }

    public function test_organization_id_is_null_when_the_underlying_project_is_missing(): void
    {
        $productions = new InMemoryProductionRepository();
        $production = Production::create(ProjectId::generate(), new ProductionName('Orphaned Show'), PersonId::generate());
        $productions->save($production);

        // Deliberately an empty Project repository - the Production's
        // own Project was never saved.
        $adapter = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));

        $this->assertNull($adapter->getProductionOrganizationId($production->id()));
    }
}
