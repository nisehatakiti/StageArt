<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\GetPublicOrganizationBySlugQuery;
use StageArt\Application\Organization\GetPublicOrganizationBySlugUseCase;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Tests\Support\InMemoryOrganizationRepository;

final class GetPublicOrganizationBySlugUseCaseTest extends TestCase
{
    public function test_returns_the_public_view_of_a_published_organization(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $useCase = new GetPublicOrganizationBySlugUseCase($organizations);

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
        $useCase = new GetPublicOrganizationBySlugUseCase($organizations);

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
        $useCase = new GetPublicOrganizationBySlugUseCase($organizations);

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
        $useCase = new GetPublicOrganizationBySlugUseCase($organizations);

        $this->expectException(OrganizationNotFoundException::class);

        $useCase->execute(new GetPublicOrganizationBySlugQuery('never-existed'));
    }
}
