<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Organization;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationStatus;

final class OrganizationTest extends TestCase
{
    public function test_create_assigns_a_unique_id_and_active_status(): void
    {
        $organization = Organization::create(new OrganizationName('Theatre Company A'));

        $this->assertSame('Theatre Company A', $organization->name()->toString());
        $this->assertSame(OrganizationStatus::ACTIVE, $organization->status()->toString());
        $this->assertNotEmpty($organization->id()->toString());
    }

    public function test_two_organizations_can_share_the_same_name(): void
    {
        $first = Organization::create(new OrganizationName('Same Name'));
        $second = Organization::create(new OrganizationName('Same Name'));

        $this->assertFalse($first->id()->equals($second->id()));
        $this->assertTrue($first->name()->equals($second->name()));
    }

    public function test_name_cannot_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationName('   ');
    }

    public function test_rename_changes_name(): void
    {
        $organization = Organization::create(new OrganizationName('Old Name'));

        $organization->rename(new OrganizationName('New Name'));

        $this->assertSame('New Name', $organization->name()->toString());
    }

    public function test_archive_transitions_status(): void
    {
        $organization = Organization::create(new OrganizationName('To Archive'));

        $organization->archive();

        $this->assertSame(OrganizationStatus::ARCHIVED, $organization->status()->toString());
    }
}
