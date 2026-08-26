<?php

declare(strict_types=1);

namespace StageArt\Tests\Core\Adapter;

use PHPUnit\Framework\TestCase;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Tests\Support\InMemoryOrganizationRepository;

final class CoreOrganizationContextAdapterTest extends TestCase
{
    public function test_true_for_an_existing_organization(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $adapter = new CoreOrganizationContextAdapter($organizations);

        $this->assertTrue($adapter->organizationExists($organization->id()));
    }

    public function test_false_for_a_nonexistent_organization(): void
    {
        $adapter = new CoreOrganizationContextAdapter(new InMemoryOrganizationRepository());

        $this->assertFalse($adapter->organizationExists(OrganizationId::generate()));
    }
}
