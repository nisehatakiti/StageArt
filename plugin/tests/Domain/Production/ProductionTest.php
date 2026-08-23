<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Production;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Domain\Project\ProjectId;

final class ProductionTest extends TestCase
{
    public function test_create_starts_in_draft_with_the_given_primary_manager(): void
    {
        $projectId = ProjectId::generate();
        $primaryManagerId = PersonId::generate();

        $production = Production::create($projectId, new ProductionName('Autumn Play'), $primaryManagerId);

        $this->assertTrue($production->projectId()->equals($projectId));
        $this->assertSame('Autumn Play', $production->name()->toString());
        $this->assertSame(ProductionStatus::DRAFT, $production->status()->toString());
        $this->assertTrue($production->primaryManagerPersonId()->equals($primaryManagerId));
    }

    public function test_rename_updates_the_name(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Old Name'), PersonId::generate());

        $production->rename(new ProductionName('New Name'));

        $this->assertSame('New Name', $production->name()->toString());
    }

    /**
     * ProductionTitleHeadingPolicy.md: "公演肩書の未設定は許可する" - unset
     * by default, and never required.
     */
    public function test_title_heading_is_null_by_default(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $this->assertNull($production->titleHeading());
    }

    public function test_create_accepts_an_initial_title_heading(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            '旗揚げ公演'
        );

        $this->assertSame('旗揚げ公演', $production->titleHeading());
    }

    public function test_change_title_heading_updates_it_independently_of_the_title(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $production->changeTitleHeading('第3回公演');

        $this->assertSame('第3回公演', $production->titleHeading());
        $this->assertSame('Show', $production->name()->toString());
    }

    /**
     * §normalizeTitleHeading: an empty/whitespace-only value is treated
     * the same as "unset", matching Organization's nullable-field
     * convention rather than storing a distinguishable empty string.
     */
    public function test_change_title_heading_to_an_empty_string_clears_it(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            '旗揚げ公演'
        );

        $production->changeTitleHeading('   ');

        $this->assertNull($production->titleHeading());
    }

    public function test_status_can_progress_through_the_basic_lifecycle_via_named_actions(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $production->startPlanning();
        $this->assertSame(ProductionStatus::PLANNING, $production->status()->toString());

        $production->activate();
        $this->assertSame(ProductionStatus::ACTIVE, $production->status()->toString());

        $production->complete();
        $this->assertSame(ProductionStatus::COMPLETED, $production->status()->toString());

        $production->archive();
        $this->assertSame(ProductionStatus::ARCHIVED, $production->status()->toString());
    }

    public function test_cancel_is_allowed_from_draft_planning_and_active(): void
    {
        $draft = Production::create(ProjectId::generate(), new ProductionName('Show A'), PersonId::generate());
        $draft->cancel();
        $this->assertSame(ProductionStatus::CANCELLED, $draft->status()->toString());

        $planning = Production::create(ProjectId::generate(), new ProductionName('Show B'), PersonId::generate());
        $planning->startPlanning();
        $planning->cancel();
        $this->assertSame(ProductionStatus::CANCELLED, $planning->status()->toString());

        $active = Production::create(ProjectId::generate(), new ProductionName('Show C'), PersonId::generate());
        $active->startPlanning();
        $active->activate();
        $active->cancel();
        $this->assertSame(ProductionStatus::CANCELLED, $active->status()->toString());
    }

    public function test_skipping_a_lifecycle_stage_is_rejected(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        // DRAFT -> ACTIVE directly (skipping PLANNING) is not an allowed transition.
        $production->activate();
    }

    public function test_completing_before_active_is_rejected(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $production->startPlanning();

        $this->expectException(InvalidArgumentException::class);

        $production->complete();
    }

    public function test_cancelling_a_completed_production_is_rejected(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $production->startPlanning();
        $production->activate();
        $production->complete();

        $this->expectException(InvalidArgumentException::class);

        $production->cancel();
    }

    public function test_archived_production_accepts_no_further_transitions(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $production->startPlanning();
        $production->activate();
        $production->complete();
        $production->archive();

        $this->expectException(InvalidArgumentException::class);

        $production->cancel();
    }

    public function test_completed_to_archived_transition_is_allowed(): void
    {
        // Phase 6.1: re-reading ProductionLifecycle.md's Completion Rule
        // confirmed the Accounting-settlement gate belongs on ACTIVE ->
        // COMPLETED (complete()), not COMPLETED -> ARCHIVED - see
        // Production::archive()'s docblock for the full reasoning behind
        // this Phase's discovered mismatch and its resolution.
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $production->startPlanning();
        $production->activate();
        $production->complete();

        $production->archive();

        $this->assertSame(ProductionStatus::ARCHIVED, $production->status()->toString());
    }

    public function test_change_primary_manager_replaces_the_reference(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $newManager = PersonId::generate();

        $production->changePrimaryManager($newManager);

        $this->assertTrue($production->primaryManagerPersonId()->equals($newManager));
    }
}
