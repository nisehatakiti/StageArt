<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Production\SearchProductionsQuery;
use StageArt\Application\Production\SearchProductionsUseCase;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class SearchProductionsUseCaseTest extends TestCase
{
    public function test_finds_a_published_production_and_resolves_its_organization(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $projects = new InMemoryProjectRepository();
        $productions = new InMemoryProductionRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, new OrganizationSlug('theatre-co'));
        $organization->publish();
        $organizations->save($organization);

        $project = Project::create($organization->id(), null);
        $projects->save($project);

        $production = Production::create(
            $project->id(),
            new ProductionName('秋の公演'),
            PersonId::generate(),
            null,
            new ProductionSlug('autumn-play')
        );
        $production->publish();
        $productions->save($production);

        $results = (new SearchProductionsUseCase($productions, $projects, $organizations))->execute(
            new SearchProductionsQuery('秋の')
        );

        $this->assertCount(1, $results);
        $this->assertSame('秋の公演', $results[0]->name);
        $this->assertSame('Theatre Co', $results[0]->organizationName);
    }

    public function test_excludes_unpublished_productions(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $projects = new InMemoryProjectRepository();
        $productions = new InMemoryProductionRepository();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);
        $project = Project::create($organization->id(), null);
        $projects->save($project);

        $draft = Production::create($project->id(), new ProductionName('秋の公演'), PersonId::generate());
        $productions->save($draft);

        $results = (new SearchProductionsUseCase($productions, $projects, $organizations))->execute(
            new SearchProductionsQuery('秋の')
        );

        $this->assertSame([], $results);
    }
}
