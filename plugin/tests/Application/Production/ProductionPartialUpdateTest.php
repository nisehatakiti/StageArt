<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\UpdateProductionCommand;
use StageArt\Application\Production\UpdateProductionUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryReservationRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * PR #7 R14-A (N1): PUT /productions/{id} changes only the fields the
 * request sent. Before R14-A a field not sent was cleared, so the publish
 * toggle (which sends name, title_heading and published) wiped the
 * description, venue, schedule, credits, capacity and duration, and the
 * duration change rewrote the Performances' end times.
 */
final class ProductionPartialUpdateTest extends TestCase
{
    private InMemoryProductionRepository $productions;
    private InMemoryPerformanceRepository $performances;
    private UpdateProductionUseCase $updateProduction;
    private Production $production;
    private Performance $performance;

    protected function setUp(): void
    {
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->performances = new InMemoryPerformanceRepository();

        $authorization = new ProductionAuthorizationService(
            new OrganizationAuthorizationService($people, $memberships),
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $this->updateProduction = new UpdateProductionUseCase(
            $this->productions,
            $authorization,
            $this->performances,
            new InMemoryTransactionManager(),
            new InMemoryReservationRepository()
        );

        $organization = Organization::create(new OrganizationName('架空劇団'));
        $manager = Person::create(1);
        $people->save($manager);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $manager->id()));
        $project = Project::create($organization->id(), 'Season');

        $this->production = Production::create($project->id(), new ProductionName('架空公演'), $manager->id());
        $this->production->changeTitleHeading('見出し');
        $this->production->changeSlug(new \StageArt\Domain\Production\ProductionSlug('kaku-kouen'));
        $this->production->updateDescription('あらすじ', new DateTimeImmutable('2026-11-01T10:00:00+09:00'));
        $this->production->updateFlyer('https://example.com/flyer.png', new DateTimeImmutable('2026-11-01T10:00:00+09:00'));
        $this->production->updateVenue('架空ホール', new DateTimeImmutable('2026-11-01T10:00:00+09:00'));
        $this->production->updateSchedule(new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2026-12-03'), new DateTimeImmutable('2026-11-01T10:00:00+09:00'));
        $this->production->updateScriptDirection('作 架空', '演出 架空', new DateTimeImmutable('2026-11-01T10:00:00+09:00'));
        $this->production->updateMemberInfoPublishedAt(new DateTimeImmutable('2026-11-01T10:00:00+09:00'));
        $this->production->updatePerformanceCommonRemarks('開場は開演の30分前');
        $this->production->updatePerformanceDurationMinutes(120);
        $this->production->changeCapacity(80);
        \StageArt\Tests\Support\ProductionStatusFixture::force($this->production, 'ACTIVE');
        $this->productions->save($this->production);

        $this->performance = Performance::create($this->production->id(), new DateTimeImmutable('2026-12-01'), '18:00', Performance::calculateEndTime('18:00', 120), 80, null, null, null, false);
        $this->performances->save($this->performance);
    }

    private function update(array $fields): Production
    {
        $value = static fn (string $key) => $fields[$key] ?? null;
        $this->updateProduction->execute(new UpdateProductionCommand(
            $this->production->id()->toString(),
            1,
            (string) ($value('name') ?? ''),
            $value('title_heading'),
            $value('slug'),
            $value('published'),
            $value('published_at'),
            $value('description'),
            $value('description_published_at'),
            $value('flyer_url'),
            $value('flyer_published_at'),
            $value('venue_name'),
            $value('venue_published_at'),
            $value('schedule_start_date'),
            $value('schedule_end_date'),
            $value('schedule_published_at'),
            $value('script_credit'),
            $value('direction_credit'),
            $value('script_direction_published_at'),
            $value('member_info_published_at'),
            $value('capacity'),
            $value('performance_common_remarks'),
            $value('performance_duration_minutes'),
            array_keys($fields)
        ));

        return $this->productions->findById(ProductionId::fromString($this->production->id()->toString()));
    }

    /** @return array<string, mixed> */
    private static function snapshot(Production $production): array
    {
        $date = static fn (?DateTimeImmutable $value): ?string => $value ? $value->format(DATE_ATOM) : null;

        return [
            'name' => $production->name()->toString(),
            'title_heading' => $production->titleHeading(),
            'description' => $production->description(),
            'description_published_at' => $date($production->descriptionPublishedAt()),
            'flyer_url' => $production->flyerUrl(),
            'flyer_published_at' => $date($production->flyerPublishedAt()),
            'venue_name' => $production->venueName(),
            'venue_published_at' => $date($production->venuePublishedAt()),
            'schedule_start_date' => $date($production->scheduleStartDate()),
            'schedule_end_date' => $date($production->scheduleEndDate()),
            'schedule_published_at' => $date($production->schedulePublishedAt()),
            'script_credit' => $production->scriptCredit(),
            'direction_credit' => $production->directionCredit(),
            'script_direction_published_at' => $date($production->scriptDirectionPublishedAt()),
            'member_info_published_at' => $date($production->memberInfoPublishedAt()),
            'performance_common_remarks' => $production->performanceCommonRemarks(),
            'performance_duration_minutes' => $production->performanceDurationMinutes(),
            'capacity' => $production->capacity(),
        ];
    }

    public function test_the_publish_toggle_keeps_every_other_field_and_the_performances(): void
    {
        $before = self::snapshot($this->production);

        // What the publish screen sends (publish.tsx).
        $after = $this->update(['name' => '架空公演', 'title_heading' => '見出し', 'published' => true]);

        $this->assertTrue($after->isPublished());
        $this->assertSame($before, self::snapshot($after));
        $performance = $this->performances->findById($this->performance->id());
        $this->assertSame('20:00', substr((string) $performance->endTime(), 0, 5), 'The Performance end time is not recalculated.');
        $this->assertSame(80, $performance->capacity());
    }

    public function test_one_field_sent_changes_only_that_field(): void
    {
        $before = self::snapshot($this->production);

        $after = $this->update(['venue_name' => '新ホール']);

        $this->assertSame(array_merge($before, ['venue_name' => '新ホール']), self::snapshot($after));
    }

    public function test_a_field_sent_as_null_is_cleared_and_the_rest_kept(): void
    {
        $before = self::snapshot($this->production);

        $after = $this->update(['description' => null, 'description_published_at' => null]);

        $this->assertSame(array_merge($before, ['description' => null, 'description_published_at' => null]), self::snapshot($after));
    }

    public function test_an_explicit_duration_change_still_reaches_the_performances(): void
    {
        $after = $this->update(['performance_duration_minutes' => 90]);

        $this->assertSame(90, $after->performanceDurationMinutes());
        $this->assertSame('19:30', substr((string) $this->performances->findById($this->performance->id())->endTime(), 0, 5));
    }

    public function test_an_explicit_capacity_change_still_reaches_the_performances(): void
    {
        $after = $this->update(['capacity' => 100]);

        $this->assertSame(100, $after->capacity());
        $this->assertSame(100, $this->performances->findById($this->performance->id())->capacity());
    }

    public function test_a_complete_form_replaces_every_field_as_before(): void
    {
        // providedFields null: every field counts as sent (the full form).
        $this->updateProduction->execute(new UpdateProductionCommand(
            $this->production->id()->toString(),
            1,
            '改題',
            null
        ));

        $after = $this->productions->findById($this->production->id());
        $this->assertSame('改題', $after->name()->toString());
        $this->assertNull($after->description());
        $this->assertNull($after->capacity());
    }
}
