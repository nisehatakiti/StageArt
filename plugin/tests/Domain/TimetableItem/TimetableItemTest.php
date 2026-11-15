<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\TimetableItem;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Timetable\TimetableId;
use StageArt\Domain\TimetableItem\TimetableItem;

final class TimetableItemTest extends TestCase
{
    public function test_create_with_minimum_required_fields(): void
    {
        $item = TimetableItem::create(
            TimetableId::generate(),
            '照明シュート',
            null,
            new DateTimeImmutable('2026-10-29 10:00:00'),
            null,
            null,
            null,
            null,
            null,
            [],
            null
        );

        $this->assertSame('照明シュート', $item->title());
        $this->assertNull($item->endDateTime());
        $this->assertSame([], $item->targetPersonIds());
    }

    public function test_create_rejects_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimetableItem::create(
            TimetableId::generate(),
            '   ',
            null,
            new DateTimeImmutable('2026-10-29 10:00:00'),
            null,
            null,
            null,
            null,
            null,
            [],
            null
        );
    }

    public function test_create_with_full_fields(): void
    {
        $personA = PersonId::generate();
        $personB = PersonId::generate();

        $item = TimetableItem::create(
            TimetableId::generate(),
            '音響バランスチェック',
            '本番前の最終確認',
            new DateTimeImmutable('2026-10-29 11:00:00'),
            new DateTimeImmutable('2026-10-29 12:00:00'),
            1,
            'TECHNICAL',
            '舞台',
            ParticipantType::staff(),
            [$personA, $personB],
            '15分前集合'
        );

        $this->assertSame('音響バランスチェック', $item->title());
        $this->assertSame('舞台', $item->venue());
        $this->assertSame(ParticipantType::STAFF, $item->participantType()->toString());
        $this->assertCount(2, $item->targetPersonIds());
        $this->assertSame('15分前集合', $item->notes());
    }

    public function test_update(): void
    {
        $item = TimetableItem::create(
            TimetableId::generate(),
            '場当たり',
            null,
            new DateTimeImmutable('2026-10-30 10:00:00'),
            null,
            null,
            null,
            null,
            null,
            [],
            null
        );

        $item->update(
            'ゲネプロ',
            '通し',
            new DateTimeImmutable('2026-10-30 13:00:00'),
            new DateTimeImmutable('2026-10-30 17:00:00'),
            2,
            'REHEARSAL',
            '舞台',
            ParticipantType::cast(),
            [],
            null
        );

        $this->assertSame('ゲネプロ', $item->title());
        $this->assertSame('舞台', $item->venue());
        $this->assertSame(ParticipantType::CAST, $item->participantType()->toString());
    }

    public function test_update_rejects_empty_title(): void
    {
        $item = TimetableItem::create(
            TimetableId::generate(),
            '搬入',
            null,
            new DateTimeImmutable('2026-10-28 09:00:00'),
            null,
            null,
            null,
            null,
            null,
            [],
            null
        );

        $this->expectException(InvalidArgumentException::class);
        $item->update('', null, new DateTimeImmutable('2026-10-28 09:00:00'), null, null, null, null, null, [], null);
    }

    /**
     * Phase 3.5 instruction §16: Role-only / Person-only / Role+Person
     * are the 3 officially supported "who uses this" patterns, expressed
     * via the existing independent participantType/targetPersonIds
     * fields - no new Field or Entity.
     */
    public function test_role_only_pattern(): void
    {
        $item = TimetableItem::create(
            TimetableId::generate(),
            '照明シュート',
            null,
            new DateTimeImmutable('2026-10-28 10:00:00'),
            null,
            null,
            null,
            null,
            ParticipantType::staff(),
            [],
            null
        );

        $this->assertNotNull($item->participantType());
        $this->assertSame([], $item->targetPersonIds());
    }

    public function test_person_only_pattern(): void
    {
        $person = PersonId::generate();

        $item = TimetableItem::create(
            TimetableId::generate(),
            '田中さん個別確認',
            null,
            new DateTimeImmutable('2026-10-28 10:00:00'),
            null,
            null,
            null,
            null,
            null,
            [$person],
            null
        );

        $this->assertNull($item->participantType());
        $this->assertCount(1, $item->targetPersonIds());
        $this->assertTrue($item->targetPersonIds()[0]->equals($person));
    }

    public function test_role_and_person_pattern(): void
    {
        $person = PersonId::generate();

        $item = TimetableItem::create(
            TimetableId::generate(),
            '照明・田中',
            null,
            new DateTimeImmutable('2026-10-28 10:00:00'),
            null,
            null,
            null,
            null,
            ParticipantType::staff(),
            [$person],
            null
        );

        $this->assertNotNull($item->participantType());
        $this->assertCount(1, $item->targetPersonIds());
    }

    /**
     * Phase 3.5 instruction §15: Category is "standard candidates + free
     * text" - any non-empty string is accepted, including values outside
     * the standard candidate list (団体独自の表現).
     */
    public function test_category_accepts_free_text_beyond_standard_candidates(): void
    {
        $item = TimetableItem::create(
            TimetableId::generate(),
            '初日ご挨拶',
            null,
            new DateTimeImmutable('2026-10-31 12:00:00'),
            null,
            null,
            '団体独自カテゴリ',
            null,
            null,
            [],
            null
        );

        $this->assertSame('団体独自カテゴリ', $item->category());
    }

    /**
     * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25): start and end are
     * full date-times; an item crossing midnight ends the next day.
     */
    private function itemAt(string $start, ?string $end): TimetableItem
    {
        return TimetableItem::create(
            TimetableId::generate(), '場当たり', null,
            new DateTimeImmutable($start), $end !== null ? new DateTimeImmutable($end) : null,
            null, null, null, null, [], null
        );
    }

    public function test_r12_an_item_may_end_the_next_day(): void
    {
        $item = $this->itemAt('2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00');
        $this->assertSame('2026-11-11T00:30:00+09:00', $item->endDateTime()->format(DATE_ATOM));

        $item->update('場当たり', null, new DateTimeImmutable('2026-11-10T23:30:00+09:00'), new DateTimeImmutable('2026-11-11T01:30:00+09:00'), null, null, null, null, [], null);
        $this->assertSame('2026-11-11T01:30:00+09:00', $item->endDateTime()->format(DATE_ATOM));
    }

    public function test_r12_create_rejects_the_same_start_and_end(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be the same');

        $this->itemAt('2026-11-10T23:30:00+09:00', '2026-11-10T23:30:00+09:00');
    }

    public function test_r12_create_rejects_an_end_before_the_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be after its start');

        $this->itemAt('2026-11-10T23:30:00+09:00', '2026-11-10T00:30:00+09:00');
    }

    public function test_r12_update_rejects_the_same_start_and_end_and_keeps_the_item(): void
    {
        $item = $this->itemAt('2026-11-10T10:00:00+09:00', '2026-11-10T12:00:00+09:00');

        try {
            $item->update('場当たり', null, new DateTimeImmutable('2026-11-10T10:00:00+09:00'), new DateTimeImmutable('2026-11-10T10:00:00+09:00'), null, null, null, null, [], null);
            $this->fail('Accepted a zero-length item.');
        } catch (InvalidArgumentException $expected) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('2026-11-10T12:00:00+09:00', $item->endDateTime()->format(DATE_ATOM));
    }

    public function test_r12_copy_keeps_stored_values_even_a_zero_length_item(): void
    {
        $stored = TimetableItem::reconstitute(
            \StageArt\Domain\TimetableItem\TimetableItemId::generate(), TimetableId::generate(), '旧データ', 'memo',
            new DateTimeImmutable('2026-11-10T10:00:00+09:00'), new DateTimeImmutable('2026-11-10T10:00:00+09:00'),
            3, 'SETUP', '舞台', ParticipantType::cast(), [PersonId::generate()], 'note',
            new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-01')
        );
        $target = TimetableId::generate();

        $copy = $stored->copyTo($target);

        $this->assertNotSame($stored->id()->toString(), $copy->id()->toString());
        $this->assertSame($target->toString(), $copy->timetableId()->toString());
        $this->assertEquals($stored->startDateTime(), $copy->startDateTime());
        $this->assertEquals($stored->endDateTime(), $copy->endDateTime());
        $this->assertSame([$stored->title(), $stored->description(), $stored->displayOrder(), $stored->category(), $stored->venue(), $stored->notes()], [$copy->title(), $copy->description(), $copy->displayOrder(), $copy->category(), $copy->venue(), $copy->notes()]);
        $this->assertEquals($stored->participantType(), $copy->participantType());
        $this->assertEquals($stored->targetPersonIds(), $copy->targetPersonIds());
    }
}
