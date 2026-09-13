<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Reservation\ReservationStatus;
use StageArt\Domain\Ticket\TicketId;
use wpdb;

final class WordPressReservationRepository implements ReservationRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_reservations';
    }

    public function save(Reservation $reservation): void
    {
        $row = [
            'reservation_number' => $reservation->reservationNumber()->toString(),
            'performance_id' => $reservation->performanceId()->toString(),
            'ticket_id' => $reservation->ticketId()->toString(),
            'booker_name' => $reservation->bookerName(),
            'booker_email' => $reservation->bookerEmail(),
            'guest_count' => $reservation->guestCount(),
            'price_snapshot' => $reservation->priceSnapshot(),
            'status' => $reservation->status()->toString(),
            'attributed_person_id' => $reservation->attributedPersonId()?->toString(),
            'updated_by' => $reservation->updatedBy()?->toString(),
            'updated_at' => $reservation->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $reservation->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $reservation->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update Reservation.');
            }

            return;
        }

        $row['id'] = $reservation->id()->toString();
        $row['created_by'] = $reservation->createdBy()?->toString();
        $row['created_at'] = $reservation->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert Reservation.');
        }
    }

    public function findById(ReservationId $id): ?Reservation
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByReservationNumber(ReservationNumber $reservationNumber): ?Reservation
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE reservation_number = %s", $reservationNumber->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPerformanceId(PerformanceId $performanceId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE performance_id = %s ORDER BY created_at ASC", $performanceId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $values = array_map(static fn (ReservationId $id): string => $id->toString(), $ids);
        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id IN ({$placeholders})", $values),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Reservation
    {
        return Reservation::reconstitute(
            ReservationId::fromString($row['id']),
            ReservationNumber::fromString($row['reservation_number']),
            PerformanceId::fromString($row['performance_id']),
            TicketId::fromString($row['ticket_id']),
            $row['booker_name'],
            $row['booker_email'],
            (int) $row['guest_count'],
            (int) $row['price_snapshot'],
            ReservationStatus::fromString($row['status']),
            ! empty($row['created_by']) ? PersonId::fromString($row['created_by']) : null,
            new DateTimeImmutable($row['created_at']),
            ! empty($row['updated_by']) ? PersonId::fromString($row['updated_by']) : null,
            new DateTimeImmutable($row['updated_at']),
            ! empty($row['attributed_person_id']) ? PersonId::fromString($row['attributed_person_id']) : null
        );
    }
}
