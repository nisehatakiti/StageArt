<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\GetPublicOrganizationBySlugQuery;
use StageArt\Application\Organization\GetPublicOrganizationBySlugUseCase;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationExternalLink;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Organization\OrganizationSNS;
use StageArt\Domain\Organization\OrganizationSNSService;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryOrganizationExternalLinkRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryOrganizationSNSRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class GetPublicOrganizationBySlugUseCaseTest extends TestCase
{
    public function test_returns_the_public_view_of_a_published_organization(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            new InMemoryProjectRepository(),
            new InMemoryProductionRepository(),
            new InMemoryOrganizationSNSRepository(),
            new InMemoryOrganizationExternalLinkRepository()
        );

        $organization = Organization::create(
            new OrganizationName('Public Theatre'),
            'nonprofit',
            'A description',
            new OrganizationSlug('public-theatre')
        );
        $organization->publish();
        $organizations->save($organization);

        $result = $useCase->execute(new GetPublicOrganizationBySlugQuery('public-theatre'));

        $this->assertSame('Public Theatre', $result->name);
        $this->assertSame('public-theatre', $result->slug);
        $this->assertSame('A description', $result->description);
    }

    /**
     * StageArt Web First Phase 2: the public DTO must never leak
     * `type`/`status`/internal fields - asserted here by construction
     * (PublicOrganizationResult has no such properties at all), not by
     * a runtime check.
     */
    public function test_public_result_never_exposes_type_or_status(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            new InMemoryProjectRepository(),
            new InMemoryProductionRepository(),
            new InMemoryOrganizationSNSRepository(),
            new InMemoryOrganizationExternalLinkRepository()
        );

        $organization = Organization::create(
            new OrganizationName('Public Theatre'),
            'internal-type-value',
            null,
            new OrganizationSlug('public-theatre')
        );
        $organization->publish();
        $organizations->save($organization);

        $result = $useCase->execute(new GetPublicOrganizationBySlugQuery('public-theatre'));
        $array = $result->toArray();

        $this->assertArrayNotHasKey('type', $array);
        $this->assertArrayNotHasKey('status', $array);
    }

    public function test_unpublished_organization_is_treated_as_not_found(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            new InMemoryProjectRepository(),
            new InMemoryProductionRepository(),
            new InMemoryOrganizationSNSRepository(),
            new InMemoryOrganizationExternalLinkRepository()
        );

        $organization = Organization::create(
            new OrganizationName('Draft Theatre'),
            null,
            null,
            new OrganizationSlug('draft-theatre')
        );
        // Deliberately never published.
        $organizations->save($organization);

        $this->expectException(OrganizationNotFoundException::class);

        $useCase->execute(new GetPublicOrganizationBySlugQuery('draft-theatre'));
    }

    public function test_nonexistent_slug_throws_the_same_exception_as_unpublished(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            new InMemoryProjectRepository(),
            new InMemoryProductionRepository(),
            new InMemoryOrganizationSNSRepository(),
            new InMemoryOrganizationExternalLinkRepository()
        );

        $this->expectException(OrganizationNotFoundException::class);

        $useCase->execute(new GetPublicOrganizationBySlugQuery('never-existed'));
    }

    /**
     * Public Page Architecture phase: only published Productions belonging
     * to this Organization (via Project) appear in the result - an
     * unpublished sibling Production must not leak through its parent
     * Organization's public page either.
     */
    public function test_only_published_productions_are_included(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $projects = new InMemoryProjectRepository();
        $productions = new InMemoryProductionRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            $projects,
            $productions,
            new InMemoryOrganizationSNSRepository(),
            new InMemoryOrganizationExternalLinkRepository()
        );

        $organization = Organization::create(
            new OrganizationName('Public Theatre'),
            null,
            null,
            new OrganizationSlug('public-theatre')
        );
        $organization->publish();
        $organizations->save($organization);

        $project = Project::create($organization->id());
        $projects->save($project);

        $published = Production::create(
            $project->id(),
            new ProductionName('Summer Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('summer-show')
        );
        $published->activate();
        $published->publish();
        $productions->save($published);

        $draft = Production::create(
            $project->id(),
            new ProductionName('Unannounced Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('unannounced-show')
        );
        // Deliberately never published.
        $productions->save($draft);

        $result = $useCase->execute(new GetPublicOrganizationBySlugQuery('public-theatre'));

        $this->assertCount(1, $result->productions);
        $this->assertSame('Summer Show', $result->productions[0]->name);
        $this->assertSame('summer-show', $result->productions[0]->slug);
    }

    public function test_scheduled_organization_before_its_publish_date_is_not_found(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            new InMemoryProjectRepository(),
            new InMemoryProductionRepository(),
            new InMemoryOrganizationSNSRepository(),
            new InMemoryOrganizationExternalLinkRepository()
        );

        $organization = Organization::create(
            new OrganizationName('Scheduled Theatre'),
            null,
            null,
            new OrganizationSlug('scheduled-theatre')
        );
        $organization->publish(new DateTimeImmutable('+1 day'));
        $organizations->save($organization);

        $this->expectException(OrganizationNotFoundException::class);

        $useCase->execute(new GetPublicOrganizationBySlugQuery('scheduled-theatre'));
    }

    public function test_scheduled_organization_after_its_publish_date_is_visible(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            new InMemoryProjectRepository(),
            new InMemoryProductionRepository(),
            new InMemoryOrganizationSNSRepository(),
            new InMemoryOrganizationExternalLinkRepository()
        );

        $organization = Organization::create(
            new OrganizationName('Now Visible Theatre'),
            null,
            null,
            new OrganizationSlug('now-visible-theatre')
        );
        $organization->publish(new DateTimeImmutable('-1 minute'));
        $organizations->save($organization);

        $result = $useCase->execute(new GetPublicOrganizationBySlugQuery('now-visible-theatre'));

        $this->assertSame('Now Visible Theatre', $result->name);
    }

    // --- StageArt 団体管理UI整理: logo/SNS/external links ---

    public function test_public_result_includes_the_logo_url(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            new InMemoryProjectRepository(),
            new InMemoryProductionRepository(),
            new InMemoryOrganizationSNSRepository(),
            new InMemoryOrganizationExternalLinkRepository()
        );

        $organization = Organization::create(
            new OrganizationName('Logo Theatre'),
            null,
            null,
            new OrganizationSlug('logo-theatre'),
            false,
            'https://example.com/logo.png'
        );
        $organization->publish();
        $organizations->save($organization);

        $result = $useCase->execute(new GetPublicOrganizationBySlugQuery('logo-theatre'));

        $this->assertSame('https://example.com/logo.png', $result->logoUrl);
    }

    public function test_public_result_includes_only_visible_sns_links_ordered_by_display_order(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $sns = new InMemoryOrganizationSNSRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            new InMemoryProjectRepository(),
            new InMemoryProductionRepository(),
            $sns,
            new InMemoryOrganizationExternalLinkRepository()
        );

        $organization = Organization::create(new OrganizationName('SNS Theatre'), null, null, new OrganizationSlug('sns-theatre'));
        $organization->publish();
        $organizations->save($organization);

        $sns->save(OrganizationSNS::create(
            $organization->id(),
            OrganizationSNSService::fromString(OrganizationSNSService::INSTAGRAM),
            'second',
            'https://instagram.com/second',
            null,
            2,
            true
        ));
        $sns->save(OrganizationSNS::create(
            $organization->id(),
            OrganizationSNSService::fromString(OrganizationSNSService::X),
            'first',
            'https://x.com/first',
            null,
            1,
            true
        ));
        $sns->save(OrganizationSNS::create(
            $organization->id(),
            OrganizationSNSService::fromString(OrganizationSNSService::X),
            'hidden',
            'https://x.com/hidden',
            null,
            0,
            false
        ));

        $result = $useCase->execute(new GetPublicOrganizationBySlugQuery('sns-theatre'));

        $this->assertCount(2, $result->sns);
        $this->assertSame('first', $result->sns[0]->accountIdentifier);
        $this->assertSame('second', $result->sns[1]->accountIdentifier);
    }

    public function test_public_result_includes_external_links(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $externalLinks = new InMemoryOrganizationExternalLinkRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase(
            $organizations,
            new InMemoryProjectRepository(),
            new InMemoryProductionRepository(),
            new InMemoryOrganizationSNSRepository(),
            $externalLinks
        );

        $organization = Organization::create(new OrganizationName('Links Theatre'), null, null, new OrganizationSlug('links-theatre'));
        $organization->publish();
        $organizations->save($organization);

        $externalLinks->save(OrganizationExternalLink::create($organization->id(), 'YouTube', 'https://youtube.com/@stageart', 0));

        $result = $useCase->execute(new GetPublicOrganizationBySlugQuery('links-theatre'));

        $this->assertCount(1, $result->externalLinks);
        $this->assertSame('YouTube', $result->externalLinks[0]->label);
    }
}
