<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Reservation;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationStatus;
use StageArt\Domain\Ticket\TicketId;

final class ReservationTest extends TestCase
{
    public function test_create_starts_reserved_with_a_generated_reservation_number(): void
    {
        $reservation = Reservation::create(
            PerformanceId::generate(),
            TicketId::generate(),
            '山田太郎',
            'yamada@example.com',
            2,
            3000,
            null
        );

        $this->assertSame(ReservationStatus::RESERVED, $reservation->status()->toString());
        $this->assertSame(2, $reservation->guestCount());
        $this->assertSame(3000, $reservation->priceSnapshot());
        $this->assertNotSame('', $reservation->reservationNumber()->toString());
        $this->assertNull($reservation->createdBy());
    }

    public function test_create_rejects_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'not-an-email', 1, 3000, null);
    }

    public function test_create_rejects_zero_guest_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 0, 3000, null);
    }

    public function test_create_rejects_non_positive_price_snapshot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 1, 0, null);
    }

    public function test_change_guest_count_updates_the_value(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 2, 3000, null);

        $reservation->changeGuestCount(4, '山田太郎', 'yamada@example.com', null);

        $this->assertSame(4, $reservation->guestCount());
    }

    public function test_change_guest_count_rejects_cancelled_reservation(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 2, 3000, null);
        $reservation->cancel(null);

        $this->expectException(InvalidArgumentException::class);
        $reservation->changeGuestCount(1, '山田太郎', 'yamada@example.com', null);
    }

    public function test_cancel_transitions_to_cancelled_and_releases_capacity(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 2, 3000, null);
        $this->assertTrue($reservation->occupiesCapacity());

        $reservation->cancel(null);

        $this->assertSame(ReservationStatus::CANCELLED, $reservation->status()->toString());
        $this->assertFalse($reservation->occupiesCapacity());
    }

    public function test_cancel_rejects_already_cancelled_reservation(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 2, 3000, null);
        $reservation->cancel(null);

        $this->expectException(InvalidArgumentException::class);
        $reservation->cancel(null);
    }

    public function test_checked_in_reservation_cannot_be_cancelled(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 2, 3000, null);
        $reservation->checkIn(null);

        $this->expectException(InvalidArgumentException::class);
        $reservation->cancel(null);
    }

    public function test_checked_in_reservation_still_occupies_capacity(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 2, 3000, null);
        $reservation->checkIn(null);

        $this->assertTrue($reservation->occupiesCapacity());
    }

    public function test_no_show_still_occupies_capacity(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 2, 3000, null);
        $reservation->markNoShow(null);

        $this->assertSame(ReservationStatus::NO_SHOW, $reservation->status()->toString());
        $this->assertTrue($reservation->occupiesCapacity());
    }

    public function test_price_snapshot_is_immutable_after_guest_count_change(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 2, 3000, null);

        $reservation->changeGuestCount(3, '山田太郎', 'yamada@example.com', null);

        $this->assertSame(3000, $reservation->priceSnapshot());
    }

    // --- Phase 4 (Check-in/精算/会計連携) additions ---

    public function test_create_defaults_to_no_attribution(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 1, 3000, null);

        $this->assertNull($reservation->attributedPersonId());
    }

    public function test_create_accepts_an_explicit_attribution(): void
    {
        $attributed = PersonId::generate();
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 1, 3000, null, $attributed);

        $this->assertTrue($reservation->attributedPersonId()->equals($attributed));
    }

    public function test_change_attribution_updates_the_value_even_after_check_in(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 1, 3000, null);
        $reservation->checkIn(null);

        $attributed = PersonId::generate();
        $reservation->changeAttribution($attributed, null);

        $this->assertTrue($reservation->attributedPersonId()->equals($attributed));
        $this->assertSame(ReservationStatus::CHECKED_IN, $reservation->status()->toString());
    }

    public function test_reverse_check_in_reverts_to_reserved(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 1, 3000, null);
        $reservation->checkIn(null);

        $reservation->reverseCheckIn(null);

        $this->assertSame(ReservationStatus::RESERVED, $reservation->status()->toString());
    }

    public function test_reverse_check_in_rejects_a_reservation_that_was_never_checked_in(): void
    {
        $reservation = Reservation::create(PerformanceId::generate(), TicketId::generate(), '山田太郎', 'yamada@example.com', 1, 3000, null);

        $this->expectException(InvalidArgumentException::class);
        $reservation->reverseCheckIn(null);
    }
}
