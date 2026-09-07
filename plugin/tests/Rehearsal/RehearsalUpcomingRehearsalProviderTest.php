<?php

declare(strict_types=1);

namespace StageArt\Tests\Rehearsal;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalStatus;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;
use StageArt\Rehearsal\RehearsalUpcomingRehearsalProvider;
use StageArt\Tests\Support\FakeProductionContextContract;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalRepository;

/**
 * StageArt Dashboard Upcoming Rehearsal timezone fix: covers the "not
 * yet ended" judgment DashboardPolicy.md requires ("過去に終了した
 * Rehearsalは今後の予定一覧に表示しない"), using real DateTimeImmutable
 * comparisons on a Rehearsal's own timezone-restored start/end - never a
 * naive DB-string comparison (see WordPressRehearsalAttendanceRepository's
 * history: it used to compare a naive local wall-clock column directly
 * against a UTC-formatted "now" string, which was wrong whenever a
 * Rehearsal's timezone differed from PHP's default).
 *
 * Deliberately bypasses CreateRehearsalUseCase/Organization/Membership/
 * Participant entirely - RehearsalUpcomingRehearsalProvider only needs a
 * RehearsalRepositoryInterface, a RehearsalAttendanceRepositoryInterface,
 * and a ProductionContextContract, so this test constructs Rehearsal/
 * RehearsalAttendance Domain Entities directly via reconstitute()/
 * createPhase1()/createPhase2() for precise, explicit control over
 * status, start/end datetime, timezone, and the "now" instant passed to
 * findUpcomingRehearsalsForPerson() - none of which a real wall-clock
 * "now" would let this test control deterministically.
 */
final class RehearsalUpcomingRehearsalProviderTest extends TestCase
{
    private InMemoryRehearsalRepository $rehearsals;
    private InMemoryRehearsalAttendanceRepository $attendances;
    private FakeProductionContextContract $productionContext;
    private RehearsalUpcomingRehearsalProvider $provider;
    private ProductionId $productionId;
    private PersonId $personId;

    protected function setUp(): void
    {
        $this->rehearsals = new InMemoryRehearsalRepository();
        $this->attendances = new InMemoryRehearsalAttendanceRepository();
        $this->productionContext = new FakeProductionContextContract();

        $this->provider = new RehearsalUpcomingRehearsalProvider(
            $this->rehearsals,
            $this->attendances,
            $this->productionContext
        );

        $this->productionId = ProductionId::generate();
        $this->personId = PersonId::generate();
        $this->productionContext->register($this->productionId, 'Show', 'ACTIVE');
    }

    /**
     * @param non-empty-string|null $status defaults to SCHEDULED (a
     *                                       Phase 1 Attendance is
     *                                       generated to match)
     */
    private function givenRehearsal(
        ?DateTimeImmutable $start,
        ?DateTimeImmutable $end,
        ?string $timezone = 'Asia/Tokyo',
        ?string $status = null
    ): Rehearsal {
        $rehearsalStatus = $status !== null ? RehearsalStatus::fromString($status) : RehearsalStatus::scheduled();

        $rehearsal = Rehearsal::reconstitute(
            RehearsalId::generate(),
            $this->productionId,
            'Rehearsal',
            null,
            $start,
            $end,
            $timezone,
            null,
            $rehearsalStatus,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->rehearsals->save($rehearsal);
        $this->attendances->registerRehearsal($rehearsal);

        $attendance = $rehearsalStatus->equals(RehearsalStatus::scheduled())
            ? RehearsalAttendance::createPhase1($rehearsal->id(), $this->personId)
            : RehearsalAttendance::createPhase2($rehearsal->id(), $this->personId);
        $this->attendances->save($attendance);

        return $rehearsal;
    }

    // --- Test 1: future Rehearsal ---------------------------------------

    public function test_future_rehearsal_is_included(): void
    {
        $now = new DateTimeImmutable('2026-01-10T00:00:00+00:00');
        $this->givenRehearsal(
            new DateTimeImmutable('2026-01-20T18:00:00+09:00'),
            new DateTimeImmutable('2026-01-20T20:00:00+09:00')
        );

        $results = $this->provider->findUpcomingRehearsalsForPerson($this->personId, $now, 50);

        $this->assertCount(1, $results);
    }

    // --- Test 2: Rehearsal currently in progress ------------------------

    public function test_in_progress_rehearsal_is_included(): void
    {
        $now = new DateTimeImmutable('2026-01-10T10:00:00+00:00'); // JST 19:00
        $this->givenRehearsal(
            new DateTimeImmutable('2026-01-10T18:00:00+09:00'), // JST 18:00 - already started
            new DateTimeImmutable('2026-01-10T21:00:00+09:00')  // JST 21:00 - not yet ended
        );

        $results = $this->provider->findUpcomingRehearsalsForPerson($this->personId, $now, 50);

        $this->assertCount(1, $results, 'A Rehearsal that has started but not yet ended must still count as upcoming.');
    }

    // --- Test 3: Rehearsal already ended --------------------------------

    public function test_ended_rehearsal_is_excluded(): void
    {
        $now = new DateTimeImmutable('2026-01-10T13:00:00+00:00'); // JST 22:00
        $this->givenRehearsal(
            new DateTimeImmutable('2026-01-10T18:00:00+09:00'), // JST 18:00
            new DateTimeImmutable('2026-01-10T20:00:00+09:00')  // JST 20:00 - already passed
        );

        $results = $this->provider->findUpcomingRehearsalsForPerson($this->personId, $now, 50);

        $this->assertSame([], $results);
    }

    // --- Test 4: endDateTime null, start still future -------------------

    public function test_null_end_date_time_falls_back_to_start_when_future(): void
    {
        $now = new DateTimeImmutable('2026-01-10T00:00:00+00:00');
        $this->givenRehearsal(
            new DateTimeImmutable('2026-01-20T18:00:00+09:00'),
            null
        );

        $results = $this->provider->findUpcomingRehearsalsForPerson($this->personId, $now, 50);

        $this->assertCount(1, $results);
    }

    // --- Test 5: endDateTime null, start already passed -----------------

    public function test_null_end_date_time_falls_back_to_start_when_past(): void
    {
        $now = new DateTimeImmutable('2026-01-10T13:00:00+00:00'); // JST 22:00
        $this->givenRehearsal(
            new DateTimeImmutable('2026-01-10T18:00:00+09:00'), // JST 18:00 - already passed
            null
        );

        $results = $this->provider->findUpcomingRehearsalsForPerson($this->personId, $now, 50);

        $this->assertSame([], $results);
    }

    // --- Test 6: COMPLETED excluded regardless of date ------------------

    public function test_completed_rehearsal_excluded_regardless_of_date(): void
    {
        $now = new DateTimeImmutable('2026-01-10T00:00:00+00:00');
        $this->givenRehearsal(
            new DateTimeImmutable('2026-01-20T18:00:00+09:00'), // future dates
            new DateTimeImmutable('2026-01-20T20:00:00+09:00'),
            'Asia/Tokyo',
            RehearsalStatus::COMPLETED
        );

        $results = $this->provider->findUpcomingRehearsalsForPerson($this->personId, $now, 50);

        $this->assertSame([], $results);
    }

    // --- Test 7: CANCELLED excluded regardless of date ------------------

    public function test_cancelled_rehearsal_excluded_regardless_of_date(): void
    {
        $now = new DateTimeImmutable('2026-01-10T00:00:00+00:00');
        $this->givenRehearsal(
            new DateTimeImmutable('2026-01-20T18:00:00+09:00'), // future dates
            new DateTimeImmutable('2026-01-20T20:00:00+09:00'),
            'Asia/Tokyo',
            RehearsalStatus::CANCELLED
        );

        $results = $this->provider->findUpcomingRehearsalsForPerson($this->personId, $now, 50);

        $this->assertSame([], $results);
    }

    // --- Test 8: timezone boundary - naive string comparison would misjudge, DateTimeImmutable is correct ---

    public function test_timezone_boundary_naive_string_comparison_would_misjudge_but_datetime_comparison_is_correct(): void
    {
        $timezone = 'Asia/Tokyo';
        $startNaive = '2026-01-10 05:00:00';
        $endNaive = '2026-01-10 06:00:00';
        $now = new DateTimeImmutable('2026-01-10T04:00:00+00:00');

        // Sanity check that this fixture actually reproduces the bug's
        // failure mode: the pre-fix WordPressRehearsalAttendanceRepository
        // compared the naive DB string directly against a
        // UTC-formatted "now" string, with no per-Rehearsal timezone
        // awareness at all.
        $this->assertTrue(
            $startNaive >= $now->format('Y-m-d H:i:s'),
            'Fixture sanity check: naive string comparison must exhibit the bug this test guards against.'
        );

        $this->givenRehearsal(
            new DateTimeImmutable($startNaive, new DateTimeZone($timezone)),
            new DateTimeImmutable($endNaive, new DateTimeZone($timezone)),
            $timezone
        );

        $results = $this->provider->findUpcomingRehearsalsForPerson($this->personId, $now, 50);

        $this->assertSame(
            [],
            $results,
            'A Rehearsal whose real (timezone-aware) end time has already passed must be excluded, ' .
            'even though a naive local-wall-clock-vs-UTC-now string comparison would suggest it is still upcoming.'
        );
    }

    // --- Sort order is unaffected by the naive-string comparison removal ---

    public function test_multiple_upcoming_rehearsals_sorted_by_real_start_date_time(): void
    {
        $now = new DateTimeImmutable('2026-01-10T00:00:00+00:00');
        $later = $this->givenRehearsal(
            new DateTimeImmutable('2026-01-25T18:00:00+09:00'),
            new DateTimeImmutable('2026-01-25T20:00:00+09:00')
        );
        $sooner = $this->givenRehearsal(
            new DateTimeImmutable('2026-01-15T18:00:00+09:00'),
            new DateTimeImmutable('2026-01-15T20:00:00+09:00')
        );

        $results = $this->provider->findUpcomingRehearsalsForPerson($this->personId, $now, 50);

        $this->assertCount(2, $results);
        $this->assertSame($sooner->id()->toString(), $results[0]->rehearsalId);
        $this->assertSame($later->id()->toString(), $results[1]->rehearsalId);
    }
}
