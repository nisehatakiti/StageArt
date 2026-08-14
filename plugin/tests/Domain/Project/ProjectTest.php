<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Project;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Project\ProjectStatus;

final class ProjectTest extends TestCase
{
    public function test_create_assigns_a_unique_id_and_draft_status(): void
    {
        $organizationId = OrganizationId::generate();
        $project = Project::create($organizationId);

        $this->assertSame(ProjectStatus::DRAFT, $project->status()->toString());
        $this->assertTrue($project->organizationId()->equals($organizationId));
        $this->assertNull($project->name());
        $this->assertNotEmpty($project->id()->toString());
    }

    public function test_name_is_optional(): void
    {
        $project = Project::create(OrganizationId::generate(), null);

        $this->assertNull($project->name());
    }

    public function test_two_projects_in_the_same_organization_can_share_a_name(): void
    {
        $organizationId = OrganizationId::generate();
        $first = Project::create($organizationId, 'Same Name');
        $second = Project::create($organizationId, 'Same Name');

        $this->assertFalse($first->id()->equals($second->id()));
        $this->assertSame('Same Name', $first->name());
        $this->assertSame('Same Name', $second->name());
    }

    public function test_status_transitions_freely_without_order_enforcement(): void
    {
        $project = Project::create(OrganizationId::generate());

        $project->changeStatus(ProjectStatus::fromString(ProjectStatus::ACTIVE));
        $this->assertSame(ProjectStatus::ACTIVE, $project->status()->toString());

        $project->changeStatus(ProjectStatus::fromString(ProjectStatus::CLOSED));
        $this->assertSame(ProjectStatus::CLOSED, $project->status()->toString());

        $project->changeStatus(ProjectStatus::fromString(ProjectStatus::ARCHIVED));
        $this->assertSame(ProjectStatus::ARCHIVED, $project->status()->toString());
    }

    public function test_archive_transitions_status_to_archived(): void
    {
        $project = Project::create(OrganizationId::generate());

        $project->archive();

        $this->assertSame(ProjectStatus::ARCHIVED, $project->status()->toString());
    }

    public function test_project_id_and_organization_id_are_immutable_across_renames_and_status_changes(): void
    {
        $organizationId = OrganizationId::generate();
        $project = Project::create($organizationId, 'Original');
        $originalId = $project->id();

        $project->rename('Renamed');
        $project->changeStatus(ProjectStatus::fromString(ProjectStatus::ACTIVE));

        $this->assertTrue($project->id()->equals($originalId));
        $this->assertTrue($project->organizationId()->equals($organizationId));
    }
}
