<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\TimetableItem\TimetableItemDateTime;
use StageArt\Application\TimetableItem\TimetableItemResult;
use StageArt\Domain\Timetable\TimetableId;
use StageArt\Domain\TimetableItem\TimetableItem;
use StageArt\Domain\TimetableItem\TimetableItemId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressTimetableItemRepository;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 R11 (docs/20-RehearsalManagementScreen.md §24): Timetable Item
 * start/end are stored as Asia/Tokyo wall-clock values. Rows written
 * before R11 already hold Asia/Tokyo wall-clock digits (every client sent
 * "+09:00" and the digits were stored as received); before R11 they were
 * read back as UTC ("10:00+00:00", shown as 19:00 on a JST device).
 *
 * The PHP default timezone is set to UTC, as WordPress forces it, and to
 * other zones - so nothing here passes only because a machine runs in JST.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressTimetableItemRepositoryTest extends TestCase
{
    /** @var array<string, array<string, mixed>> stageart_timetable_items rows by id */
    private array $rows = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        date_default_timezone_set('UTC');
    }

    private function repository(): WordPressTimetableItemRepository
    {
        $wpdb = new \wpdb(function (string $method, array $args, FakeWpdb $db) {
            $itemsTable = $db->prefix . 'stageart_timetable_items';

            if ($method === 'insert' && $args[0] === $itemsTable) {
                $this->rows[$args[1]['id']] = $args[1];

                return 1;
            }

            if ($method === 'update' && $args[0] === $itemsTable) {
                $this->rows[$args[2]['id']] = array_merge($this->rows[$args[2]['id']], $args[1]);

                return 1;
            }

            if ($method === 'get_var' || $method === 'get_row') {
                $prepared = $db->preparedFrom($args[0]);

                if ($prepared !== null && strpos($prepared['template'], $itemsTable) !== false) {
                    $row = $this->rows[$prepared['args'][0]] ?? null;

                    if ($method === 'get_var') {
                        return $row !== null ? $row['id'] : null;
                    }

                    return $row;
                }
            }

            return FakeWpdbDefault::VALUE;
        });

        return new WordPressTimetableItemRepository($wpdb);
    }

    /**
     * A row exactly as the pre-R11 code wrote it for an item entered as
     * 10:00-12:00 JST (the app sent "2026-08-20T10:00:00+09:00").
     */
    private function givenLegacyRow(string $start = '2026-08-20 10:00:00', ?string $end = '2026-08-20 12:00:00'): TimetableItemId
    {
        $id = TimetableItemId::generate();
        $this->rows[$id->toString()] = [
            'id' => $id->toString(),
            'timetable_id' => TimetableId::generate()->toString(),
            'title' => '場当たり',
            'description' => null,
            'start_date_time' => $start,
            'end_date_time' => $end,
            'display_order' => '1',
            'category' => null,
            'venue' => null,
            'participant_type' => null,
            'notes' => null,
            'created_at' => '2026-08-01 03:00:00',
            'updated_at' => '2026-08-01 03:00:00',
        ];

        return $id;
    }

    private function newItem(string $start, ?string $end): TimetableItem
    {
        return TimetableItem::create(
            TimetableId::generate(),
            '場当たり',
            null,
            TimetableItemDateTime::parseRequired($start, 'start_date_time'),
            TimetableItemDateTime::parseOptional($end),
            1,
            null,
            null,
            null,
            [],
            null
        );
    }

    public function test_a_row_written_before_r11_reads_as_the_same_jst_time(): void
    {
        foreach (['UTC', 'America/New_York', 'Asia/Tokyo'] as $phpTimezone) {
            date_default_timezone_set($phpTimezone);
            $id = $this->givenLegacyRow();

            $result = TimetableItemResult::fromDomain($this->repository()->findById($id))->toArray();

            $this->assertSame('2026-08-20T10:00:00+09:00', $result['start_date_time'], $phpTimezone);
            $this->assertSame('2026-08-20T12:00:00+09:00', $result['end_date_time'], $phpTimezone);
        }
    }

    public function test_reading_and_saving_a_pre_r11_row_does_not_change_its_stored_value(): void
    {
        $id = $this->givenLegacyRow('2026-12-31 23:30:00', '2027-01-01 00:30:00');
        $repository = $this->repository();

        for ($i = 0; $i < 3; $i++) {
            $repository->save($repository->findById($id));
        }

        $this->assertSame('2026-12-31 23:30:00', $this->rows[$id->toString()]['start_date_time']);
        $this->assertSame('2027-01-01 00:30:00', $this->rows[$id->toString()]['end_date_time']);
    }

    public function test_a_10_00_jst_input_is_stored_as_10_00_whatever_offset_it_arrived_with(): void
    {
        $repository = $this->repository();

        foreach (['2026-08-20T10:00:00+09:00', '2026-08-20T01:00:00Z', '2026-08-19T20:00:00-05:00', '2026-08-20T10:00:00'] as $input) {
            $item = $this->newItem($input, null);
            $repository->save($item);

            $this->assertSame('2026-08-20 10:00:00', $this->rows[$item->id()->toString()]['start_date_time'], $input);
            $this->assertNull($this->rows[$item->id()->toString()]['end_date_time'], $input);
            $this->assertSame(
                '2026-08-20T10:00:00+09:00',
                TimetableItemResult::fromDomain($repository->findById($item->id()))->startDateTime,
                $input
            );
        }
    }

    public function test_the_new_storage_format_is_the_same_as_the_pre_r11_one_for_app_input(): void
    {
        // What the pre-R11 save() wrote for the app's "+09:00" input.
        $appInput = '2026-08-20T10:00:00+09:00';
        $preR11Stored = (new DateTimeImmutable($appInput))->format('Y-m-d H:i:s');

        $item = $this->newItem($appInput, null);
        $this->repository()->save($item);

        $this->assertSame($preR11Stored, $this->rows[$item->id()->toString()]['start_date_time']);
    }

    public function test_dates_across_the_utc_and_jst_day_and_year_boundaries(): void
    {
        $repository = $this->repository();
        $item = $this->newItem('2026-12-31T15:30:00Z', '2026-12-31T16:00:00Z');
        $repository->save($item);

        $this->assertSame('2027-01-01 00:30:00', $this->rows[$item->id()->toString()]['start_date_time']);
        $this->assertSame('2027-01-01 01:00:00', $this->rows[$item->id()->toString()]['end_date_time']);

        $result = TimetableItemResult::fromDomain($repository->findById($item->id()));
        $this->assertSame('2027-01-01T00:30:00+09:00', $result->startDateTime);
        $this->assertSame('2027-01-01T01:00:00+09:00', $result->endDateTime);
    }

    public function test_re_sending_the_api_value_gives_the_same_stored_value(): void
    {
        $repository = $this->repository();
        $id = $this->givenLegacyRow();

        for ($i = 0; $i < 3; $i++) {
            $item = $repository->findById($id);
            $apiValue = TimetableItemResult::fromDomain($item)->toArray();
            $item->update(
                $item->title(),
                $item->description(),
                TimetableItemDateTime::parseRequired($apiValue['start_date_time'], 'start_date_time'),
                TimetableItemDateTime::parseOptional($apiValue['end_date_time']),
                $item->displayOrder(),
                $item->category(),
                $item->venue(),
                $item->participantType(),
                $item->targetPersonIds(),
                $item->notes()
            );
            $repository->save($item);
        }

        $this->assertSame('2026-08-20 10:00:00', $this->rows[$id->toString()]['start_date_time']);
        $this->assertSame('2026-08-20 12:00:00', $this->rows[$id->toString()]['end_date_time']);
    }

    /**
     * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25.3): before R12 the
     * editor stored a 23:30-00:30 item with its end on the START date
     * (end before start). Such a row is read as ending the next day; the
     * stored value is only rewritten when that item is saved.
     */
    public function test_r12_a_pre_r12_cross_midnight_row_reads_as_ending_the_next_day(): void
    {
        foreach (['UTC', 'America/New_York', 'Asia/Tokyo'] as $phpTimezone) {
            date_default_timezone_set($phpTimezone);
            $a = $this->givenLegacyRow('2026-11-10 23:30:00', '2026-11-10 00:30:00');
            $b = $this->givenLegacyRow('2026-11-10 23:30:00', '2026-11-10 01:30:00');
            $repository = $this->repository();

            $this->assertSame('2026-11-11T00:30:00+09:00', TimetableItemResult::fromDomain($repository->findById($a))->endDateTime, $phpTimezone);
            $this->assertSame('2026-11-11T01:30:00+09:00', TimetableItemResult::fromDomain($repository->findById($b))->endDateTime, $phpTimezone);
            $this->assertSame('2026-11-10 00:30:00', $this->rows[$a->toString()]['end_date_time'], 'Reading does not change the stored value.');
        }
    }

    public function test_r12_saving_a_pre_r12_cross_midnight_row_keeps_its_meaning(): void
    {
        $id = $this->givenLegacyRow('2026-11-10 23:30:00', '2026-11-10 00:30:00');
        $repository = $this->repository();

        for ($i = 0; $i < 3; $i++) {
            $repository->save($repository->findById($id));
            $result = TimetableItemResult::fromDomain($repository->findById($id));
            $this->assertSame(['2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00'], [$result->startDateTime, $result->endDateTime]);
        }

        $this->assertSame('2026-11-10 23:30:00', $this->rows[$id->toString()]['start_date_time']);
        $this->assertSame('2026-11-11 00:30:00', $this->rows[$id->toString()]['end_date_time']);
    }

    public function test_r12_other_rows_read_as_stored(): void
    {
        $repository = $this->repository();
        $sameDay = $this->givenLegacyRow('2026-11-10 10:00:00', '2026-11-10 12:00:00');
        $zeroLength = $this->givenLegacyRow('2026-11-10 10:00:00', '2026-11-10 10:00:00');
        $nextDay = $this->givenLegacyRow('2026-11-10 23:30:00', '2026-11-11 00:30:00');
        $noEnd = $this->givenLegacyRow('2026-11-10 23:30:00', null);

        $this->assertSame('2026-11-10T12:00:00+09:00', TimetableItemResult::fromDomain($repository->findById($sameDay))->endDateTime);
        $this->assertSame('2026-11-10T10:00:00+09:00', TimetableItemResult::fromDomain($repository->findById($zeroLength))->endDateTime);
        $this->assertSame('2026-11-11T00:30:00+09:00', TimetableItemResult::fromDomain($repository->findById($nextDay))->endDateTime);
        $this->assertNull(TimetableItemResult::fromDomain($repository->findById($noEnd))->endDateTime);
    }

    public function test_r12_a_new_cross_midnight_item_is_stored_with_its_next_day_end(): void
    {
        $item = $this->newItem('2026-12-31T23:30:00+09:00', '2027-01-01T01:30:00+09:00');
        $this->repository()->save($item);

        $this->assertSame('2026-12-31 23:30:00', $this->rows[$item->id()->toString()]['start_date_time']);
        $this->assertSame('2027-01-01 01:30:00', $this->rows[$item->id()->toString()]['end_date_time']);
    }
}
