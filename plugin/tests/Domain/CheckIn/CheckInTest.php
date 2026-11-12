<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\CheckIn;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\CheckIn\CheckIn;
use StageArt\Domain\CheckIn\CheckInStatus;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Reservation\ReservationId;

final class CheckInTest extends TestCase
{
    public function test_complete_starts_completed_with_checked_in_by_and_at(): void
    {
        $reservationId = ReservationId::generate();
        $performanceId = PerformanceId::generate();
        $staff = PersonId::generate();

        $checkIn = CheckIn::complete($reservationId, $performanceId, $staff);

        $this->assertTrue($checkIn->isCompleted());
        $this->assertTrue($checkIn->reservationId()->equals($reservationId));
        $this->assertTrue($checkIn->performanceId()->equals($performanceId));
        $this->assertTrue($checkIn->checkedInBy()->equals($staff));
        $this->assertNull($checkIn->reversedBy());
        $this->assertNull($checkIn->reversedAt());
    }

    public function test_reverse_transitions_to_reversed_and_records_who_and_when(): void
    {
        $checkIn = CheckIn::complete(ReservationId::generate(), PerformanceId::generate(), PersonId::generate());
        $reverser = PersonId::generate();

        $checkIn->reverse($reverser);

        $this->assertFalse($checkIn->isCompleted());
        $this->assertSame(CheckInStatus::REVERSED, $checkIn->status()->toString());
        $this->assertTrue($checkIn->reversedBy()->equals($reverser));
        $this->assertNotNull($checkIn->reversedAt());
    }

    public function test_reverse_rejects_an_already_reversed_check_in(): void
    {
        $checkIn = CheckIn::complete(ReservationId::generate(), PerformanceId::generate(), PersonId::generate());
        $checkIn->reverse(PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $checkIn->reverse(PersonId::generate());
    }

    public function test_reversal_does_not_erase_the_original_checked_in_by_and_at(): void
    {
        // CheckIn.md "# Reversed": 物理削除しない - the original Check-in
        // Fact's own audit fields must survive a Reversal unchanged.
        $staff = PersonId::generate();
        $checkIn = CheckIn::complete(ReservationId::generate(), PerformanceId::generate(), $staff);
        $originalCheckedInAt = $checkIn->checkedInAt();

        $checkIn->reverse(PersonId::generate());

        $this->assertTrue($checkIn->checkedInBy()->equals($staff));
        $this->assertEquals($originalCheckedInAt, $checkIn->checkedInAt());
    }
}
