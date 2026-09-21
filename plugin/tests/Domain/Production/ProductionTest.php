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
    public function test_create_starts_in_planning_with_the_given_primary_manager(): void
    {
        $projectId = ProjectId::generate();
        $primaryManagerId = PersonId::generate();

        $production = Production::create($projectId, new ProductionName('Autumn Play'), $primaryManagerId);

        $this->assertTrue($production->projectId()->equals($projectId));
        $this->assertSame('Autumn Play', $production->name()->toString());
        $this->assertSame(ProductionStatus::PLANNING, $production->status()->toString());
        $this->assertTrue($production->primaryManagerPersonId()->equals($primaryManagerId));
    }

    /**
     * StageArt Production Lifecycle整理 instruction: "「公演を作る」を押した
     * 直後はPLANNING" and "PLANNINGが非公開" - a newly created Production is
     * never published, regardless of slug.
     */
    public function test_a_newly_created_production_is_not_published(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('fresh-show')
        );

        $this->assertFalse($production->isPublished());
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

    /**
     * StageArt Production Lifecycle整理 instruction: the confirmed chain
     * this round is PLANNING -> ACTIVE -> COMPLETED. A Production already
     * starts at PLANNING (create() no longer produces DRAFT), so
     * activate() is the very next Action - no separate "start planning"
     * step exists anymore. ARCHIVED is kept reachable exactly as before
     * (its retention was not decided this round).
     */
    public function test_status_can_progress_through_the_basic_lifecycle_via_named_actions(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('lifecycle-show')
        );
        $this->assertSame(ProductionStatus::PLANNING, $production->status()->toString());

        $production->activate();
        $this->assertSame(ProductionStatus::ACTIVE, $production->status()->toString());

        $production->complete();
        $this->assertSame(ProductionStatus::COMPLETED, $production->status()->toString());

        $production->archive();
        $this->assertSame(ProductionStatus::ARCHIVED, $production->status()->toString());
    }

    /**
     * "この処理（公演を確定する）によって、公開状態になります" - activate()
     * (PLANNING -> ACTIVE) also publishes the Production.
     */
    public function test_activating_publishes_the_production(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('activate-publishes')
        );
        $this->assertFalse($production->isPublished());

        $production->activate();

        $this->assertTrue($production->isPublished());
        $this->assertNotNull($production->publishedAt());
    }

    public function test_cancel_is_allowed_from_planning_and_active(): void
    {
        $planning = Production::create(ProjectId::generate(), new ProductionName('Show A'), PersonId::generate());
        $planning->cancel();
        $this->assertSame(ProductionStatus::CANCELLED, $planning->status()->toString());

        $active = Production::create(
            ProjectId::generate(),
            new ProductionName('Show B'),
            PersonId::generate(),
            null,
            new ProductionSlug('cancel-from-active')
        );
        $active->activate();
        $active->cancel();
        $this->assertSame(ProductionStatus::CANCELLED, $active->status()->toString());
    }

    public function test_activating_an_already_active_production_is_rejected(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('already-active')
        );
        $production->activate();

        $this->expectException(InvalidArgumentException::class);

        $production->activate();
    }

    public function test_completing_before_active_is_rejected(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        $production->complete();
    }

    public function test_cancelling_a_completed_production_is_rejected(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('cancel-completed')
        );
        $production->activate();
        $production->complete();

        $this->expectException(InvalidArgumentException::class);

        $production->cancel();
    }

    public function test_archived_production_accepts_no_further_transitions(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('archived-no-further')
        );
        $production->activate();
        $production->complete();
        $production->archive();

        $this->expectException(InvalidArgumentException::class);

        $production->cancel();
    }

    public function test_completed_to_archived_transition_is_allowed(): void
    {
        $production = Production::create(
            ProjectId::generate(),
            new ProductionName('Show'),
            PersonId::generate(),
            null,
            new ProductionSlug('completed-to-archived')
        );
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

    public function test_a_new_production_has_no_information_section_content_or_publication_dates(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $this->assertNull($production->description());
        $this->assertNull($production->descriptionPublishedAt());
        $this->assertNull($production->flyerUrl());
        $this->assertNull($production->venueName());
        $this->assertNull($production->scheduleStartDate());
        $this->assertNull($production->scheduleEndDate());
        $this->assertNull($production->scriptCredit());
        $this->assertNull($production->directionCredit());
    }

    public function test_update_description_sets_value_and_its_own_publication_date(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $publishedAt = new DateTimeImmutable('2026-10-01 00:00:00');

        $production->updateDescription('あらすじ本文', $publishedAt);

        $this->assertSame('あらすじ本文', $production->description());
        $this->assertEquals($publishedAt, $production->descriptionPublishedAt());
    }

    public function test_update_description_normalizes_blank_string_to_null(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $production->updateDescription('   ', null);

        $this->assertNull($production->description());
    }

    public function test_update_venue_sets_value_and_its_own_publication_date(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $publishedAt = new DateTimeImmutable('2026-10-01 00:00:00');

        $production->updateVenue('○○ホール', $publishedAt);

        $this->assertSame('○○ホール', $production->venueName());
        $this->assertEquals($publishedAt, $production->venuePublishedAt());
    }

    public function test_update_schedule_sets_start_end_and_publication_date(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $start = new DateTimeImmutable('2026-10-10');
        $end = new DateTimeImmutable('2026-10-12');
        $publishedAt = new DateTimeImmutable('2026-09-01 00:00:00');

        $production->updateSchedule($start, $end, $publishedAt);

        $this->assertEquals($start, $production->scheduleStartDate());
        $this->assertEquals($end, $production->scheduleEndDate());
        $this->assertEquals($publishedAt, $production->schedulePublishedAt());
    }

    public function test_update_schedule_rejects_an_end_date_before_the_start_date(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);

        $production->updateSchedule(new DateTimeImmutable('2026-10-12'), new DateTimeImmutable('2026-10-10'), null);
    }

    public function test_update_flyer_sets_url_and_its_own_publication_date(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $publishedAt = new DateTimeImmutable('2026-10-01 00:00:00');

        $production->updateFlyer('https://example.com/flyer.jpg', $publishedAt);

        $this->assertSame('https://example.com/flyer.jpg', $production->flyerUrl());
        $this->assertEquals($publishedAt, $production->flyerPublishedAt());
    }

    public function test_update_script_direction_sets_both_credits_and_one_shared_publication_date(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $publishedAt = new DateTimeImmutable('2026-10-01 00:00:00');

        $production->updateScriptDirection('山田太郎', '鈴木花子', $publishedAt);

        $this->assertSame('山田太郎', $production->scriptCredit());
        $this->assertSame('鈴木花子', $production->directionCredit());
        $this->assertEquals($publishedAt, $production->scriptDirectionPublishedAt());
    }

    public function test_update_member_info_published_at_sets_the_one_shared_date_for_the_whole_roster(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $this->assertNull($production->memberInfoPublishedAt());

        $publishedAt = new DateTimeImmutable('2026-10-01 00:00:00');
        $production->updateMemberInfoPublishedAt($publishedAt);

        $this->assertEquals($publishedAt, $production->memberInfoPublishedAt());
    }

    public function test_capacity_is_null_by_default(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $this->assertNull($production->capacity());
    }

    public function test_change_capacity_sets_the_value(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $production->changeCapacity(100);

        $this->assertSame(100, $production->capacity());
    }

    public function test_change_capacity_rejects_non_positive_value(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $production->changeCapacity(0);
    }

    public function test_change_capacity_allows_clearing_back_to_null(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $production->changeCapacity(100);

        $production->changeCapacity(null);

        $this->assertNull($production->capacity());
    }

    public function test_update_performance_common_remarks_sets_and_trims_the_value(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());

        $production->updatePerformanceCommonRemarks('  開場は開演30分前です  ');

        $this->assertSame('開場は開演30分前です', $production->performanceCommonRemarks());
    }

    public function test_update_performance_common_remarks_normalizes_empty_string_to_null(): void
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $production->updatePerformanceCommonRemarks('some notes');

        $production->updatePerformanceCommonRemarks('');

        $this->assertNull($production->performanceCommonRemarks());
    }
}
