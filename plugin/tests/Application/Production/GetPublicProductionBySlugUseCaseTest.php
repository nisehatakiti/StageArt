<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Production\GetPublicProductionBySlugQuery;
use StageArt\Application\Production\GetPublicProductionBySlugUseCase;
use StageArt\Application\Production\ProductionNotFoundException;
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

final class GetPublicProductionBySlugUseCaseTest extends TestCase
{
    private function useCase(
        InMemoryProductionRepository $productions,
        InMemoryProjectRepository $projects,
        InMemoryOrganizationRepository $organizations
    ): GetPublicProductionBySlugUseCase {
        return new GetPublicProductionBySlugUseCase($productions, $projects, $organizations);
    }

    /**
     * @return array{0: Organization, 1: Project} the published parent
     *                                              Organization/Project,
     *                                              already saved.
     */
    private function givenPublishedOrganizationWithProject(
        InMemoryOrganizationRepository $organizations,
        InMemoryProjectRepository $projects
    ): array {
        $organization = Organization::create(
            new OrganizationName('Theatre Co'),
            null,
            null,
            new OrganizationSlug('theatre-co')
        );
        $organization->publish();
        $organizations->save($organization);

        $project = Project::create($organization->id(), null);
        $projects->save($project);

        return [$organization, $project];
    }

    public function test_returns_the_public_view_with_organization_identity(): void
    {
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        [$organization, $project] = $this->givenPublishedOrganizationWithProject($organizations, $projects);

        $production = Production::create(
            $project->id(),
            new ProductionName('Autumn Play'),
            PersonId::generate(),
            null,
            new ProductionSlug('autumn-play')
        );
        $production->publish();
        $productions->save($production);

        $result = $this->useCase($productions, $projects, $organizations)->execute(
            new GetPublicProductionBySlugQuery('autumn-play')
        );

        $this->assertSame('Autumn Play', $result->name);
        $this->assertSame('autumn-play', $result->slug);
        $this->assertSame($organization->id()->toString(), $result->organizationId);
        $this->assertSame('Theatre Co', $result->organizationName);
        $this->assertSame('theatre-co', $result->organizationSlug);
    }

    public function test_public_result_never_exposes_status_or_primary_manager(): void
    {
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        [, $project] = $this->givenPublishedOrganizationWithProject($organizations, $projects);

        $production = Production::create(
            $project->id(),
            new ProductionName('Autumn Play'),
            PersonId::generate(),
            null,
            new ProductionSlug('autumn-play')
        );
        $production->publish();
        $productions->save($production);

        $result = $this->useCase($productions, $projects, $organizations)->execute(
            new GetPublicProductionBySlugQuery('autumn-play')
        );
        $array = $result->toArray();

        $this->assertArrayNotHasKey('status', $array);
        $this->assertArrayNotHasKey('primary_manager_person_id', $array);
    }

    public function test_unpublished_production_is_treated_as_not_found_even_if_organization_is_published(): void
    {
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        [, $project] = $this->givenPublishedOrganizationWithProject($organizations, $projects);

        $production = Production::create(
            $project->id(),
            new ProductionName('Draft Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('draft-show')
        );
        // Deliberately never published.
        $productions->save($production);

        $this->expectException(ProductionNotFoundException::class);

        $this->useCase($productions, $projects, $organizations)->execute(
            new GetPublicProductionBySlugQuery('draft-show')
        );
    }

    public function test_nonexistent_slug_throws_the_same_exception_as_unpublished(): void
    {
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        $this->expectException(ProductionNotFoundException::class);

        $this->useCase($productions, $projects, $organizations)->execute(
            new GetPublicProductionBySlugQuery('never-existed')
        );
    }

    /**
     * Publication State Model: SCHEDULED (publishedAt in the future) is
     * not yet publicly visible - identical 404 to a never-published one.
     */
    public function test_scheduled_production_before_its_publish_date_is_not_found(): void
    {
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        [, $project] = $this->givenPublishedOrganizationWithProject($organizations, $projects);

        $production = Production::create(
            $project->id(),
            new ProductionName('Scheduled Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('scheduled-show')
        );
        $production->publish(new DateTimeImmutable('+1 day'));
        $productions->save($production);

        $this->expectException(ProductionNotFoundException::class);

        $this->useCase($productions, $projects, $organizations)->execute(
            new GetPublicProductionBySlugQuery('scheduled-show')
        );
    }

    public function test_scheduled_production_after_its_publish_date_is_visible(): void
    {
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $organizations = new InMemoryOrganizationRepository();

        [, $project] = $this->givenPublishedOrganizationWithProject($organizations, $projects);

        $production = Production::create(
            $project->id(),
            new ProductionName('Now Visible Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('now-visible-show')
        );
        $production->publish(new DateTimeImmutable('-1 minute'));
        $productions->save($production);

        $result = $this->useCase($productions, $projects, $organizations)->execute(
            new GetPublicProductionBySlugQuery('now-visible-show')
        );

        $this->assertSame('Now Visible Show', $result->name);
    }
}
