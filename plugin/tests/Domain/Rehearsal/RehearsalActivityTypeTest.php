<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Rehearsal;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Rehearsal\RehearsalActivityType;

final class RehearsalActivityTypeTest extends TestCase
{
    public function test_rehearsal_default(): void
    {
        $this->assertSame(RehearsalActivityType::REHEARSAL, RehearsalActivityType::rehearsal()->toString());
    }

    /**
     * @dataProvider validValueProvider
     */
    public function test_accepts_every_confirmed_species(string $value): void
    {
        $this->assertSame($value, RehearsalActivityType::fromString($value)->toString());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validValueProvider(): array
    {
        return [
            'REHEARSAL' => [RehearsalActivityType::REHEARSAL],
            'PRACTICE' => [RehearsalActivityType::PRACTICE],
            'SELF_PRACTICE' => [RehearsalActivityType::SELF_PRACTICE],
            'WORKSHOP' => [RehearsalActivityType::WORKSHOP],
            'MEETING' => [RehearsalActivityType::MEETING],
            'DISCUSSION' => [RehearsalActivityType::DISCUSSION],
            'EVENT' => [RehearsalActivityType::EVENT],
            'OTHER' => [RehearsalActivityType::OTHER],
        ];
    }

    public function test_rejects_an_unknown_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RehearsalActivityType::fromString('WORKSHOP_TYPO');
    }

    /**
     * StageArt 予定・活動一般化調査: these show-run/performance-progression
     * concepts belong to Timetable, never to this VO (see Rehearsal's own
     * class docblock) - a typo'd attempt to use one of them as an
     * activity_type must be rejected exactly like any other invalid value.
     *
     * @dataProvider timetableOwnedValueProvider
     */
    public function test_rejects_values_owned_by_timetable(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        RehearsalActivityType::fromString($value);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function timetableOwnedValueProvider(): array
    {
        return [
            'PERFORMANCE' => ['PERFORMANCE'],
            'LOAD_IN' => ['LOAD_IN'],
            'SETUP' => ['SETUP'],
            'TECH_REHEARSAL' => ['TECH_REHEARSAL'],
            'DRESS_REHEARSAL' => ['DRESS_REHEARSAL'],
        ];
    }

    public function test_equals(): void
    {
        $a = RehearsalActivityType::fromString(RehearsalActivityType::WORKSHOP);
        $b = RehearsalActivityType::fromString(RehearsalActivityType::WORKSHOP);
        $c = RehearsalActivityType::fromString(RehearsalActivityType::MEETING);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
