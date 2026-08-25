<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\SearchOrganizationsQuery;
use StageArt\Application\Organization\SearchOrganizationsUseCase;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Tests\Support\InMemoryOrganizationRepository;

final class SearchOrganizationsUseCaseTest extends TestCase
{
    public function test_finds_published_organizations_matching_the_query(): void
    {
        $organizations = new InMemoryOrganizationRepository();

        $match = Organization::create(new OrganizationName('劇団あおぞら'), null, null, new OrganizationSlug('aozora'));
        $match->publish();
        $organizations->save($match);

        $noMatch = Organization::create(new OrganizationName('企画集団ひかり'), null, null, new OrganizationSlug('hikari'));
        $noMatch->publish();
        $organizations->save($noMatch);

        $results = (new SearchOrganizationsUseCase($organizations))->execute(new SearchOrganizationsQuery('あおぞら'));

        $this->assertCount(1, $results);
        $this->assertSame('劇団あおぞら', $results[0]->name);
    }

    public function test_excludes_unpublished_organizations(): void
    {
        $organizations = new InMemoryOrganizationRepository();

        $draft = Organization::create(new OrganizationName('劇団あおぞら'));
        $organizations->save($draft);

        $results = (new SearchOrganizationsUseCase($organizations))->execute(new SearchOrganizationsQuery('あおぞら'));

        $this->assertSame([], $results);
    }

    public function test_an_empty_query_returns_no_results(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $organization = Organization::create(new OrganizationName('劇団あおぞら'), null, null, new OrganizationSlug('aozora'));
        $organization->publish();
        $organizations->save($organization);

        $results = (new SearchOrganizationsUseCase($organizations))->execute(new SearchOrganizationsQuery('  '));

        $this->assertSame([], $results);
    }
}
