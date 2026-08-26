<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Production;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
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

    public function test_a_new_production_without_a_slug_is_unpublished(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $this->assertNull($production->slug());
        $this->assertNull($production->publishedAt());
        $this->assertFalse($production->isPublished());
    }

    public function test_publishing_requires_a_slug(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        $production->publish();
    }

    public function test_publish_sets_published_at_when_a_slug_is_present(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('ready-to-publish')
        );

        $production->publish();

        $this->assertTrue($production->isPublished());
        $this->assertNotNull($production->publishedAt());
    }

    public function test_unpublish_clears_published_at(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('ready-to-publish')
        );
        $production->publish();

        $production->unpublish();

        $this->assertFalse($production->isPublished());
        $this->assertNull($production->publishedAt());
    }

    /**
     * Publication State Model (docs/04-DomainModel/PublicationStateModel.md):
     * a future `publish($at)` is SCHEDULED - `publishedAt` is set, but
     * `isPublished()` stays false until that moment passes.
     */
    public function test_publish_with_a_future_date_is_scheduled_not_yet_published(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Scheduled Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('scheduled-show')
        );

        $production->publish(new DateTimeImmutable('+1 day'));

        $this->assertFalse($production->isPublished());
        $this->assertNotNull($production->publishedAt());
    }

    public function test_publish_with_a_past_date_is_immediately_published(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Past Scheduled Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('past-scheduled-show')
        );

        $production->publish(new DateTimeImmutable('-1 day'));

        $this->assertTrue($production->isPublished());
    }

    public function test_change_slug_updates_the_slug(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('old-slug')
        );

        $production->changeSlug(new ProductionSlug('new-slug'));

        $this->assertSame('new-slug', $production->slug()?->toString());
    }
}
