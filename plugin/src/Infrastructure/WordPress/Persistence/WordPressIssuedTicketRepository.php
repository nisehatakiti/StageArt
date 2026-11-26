<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\IssuedTicket\IssuedTicket;
use StageArt\Domain\IssuedTicket\IssuedTicketId;
use StageArt\Domain\IssuedTicket\IssuedTicketRepositoryInterface;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Ticket\TicketId;
use wpdb;

final class WordPressIssuedTicketRepository implements IssuedTicketRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_issued_tickets';
    }

    public function save(IssuedTicket $issuedTicket): void
    {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $issuedTicket->id()->toString())
        );

        if ($existing) {
            // IssuedTicket is immutable once created this Phase - nothing
            // to update, but save() stays idempotent rather than throwing.
            return;
        }

        $result = $this->wpdb->insert($this->table, [
            'id' => $issuedTicket->id()->toString(),
            'reservation_id' => $issuedTicket->reservationId()->toString(),
            'performance_id' => $issuedTicket->performanceId()->toString(),
            'ticket_id' => $issuedTicket->ticketId()->toString(),
            'guest_count' => $issuedTicket->guestCount(),
            'issued_at' => $issuedTicket->issuedAt()->format('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            throw new RuntimeException('Failed to insert Issued Ticket.');
        }
    }

    public function findById(IssuedTicketId $id): ?IssuedTicket
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByReservationId(ReservationId $reservationId): ?IssuedTicket
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE reservation_id = %s", $reservationId->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): IssuedTicket
    {
        return IssuedTicket::reconstitute(
            IssuedTicketId::fromString($row['id']),
            ReservationId::fromString($row['reservation_id']),
            PerformanceId::fromString($row['performance_id']),
            TicketId::fromString($row['ticket_id']),
            (int) $row['guest_count'],
            new DateTimeImmutable($row['issued_at'])
        );
    }
}
