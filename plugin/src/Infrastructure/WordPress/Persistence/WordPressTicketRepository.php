<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Domain\Ticket\TicketId;
use StageArt\Domain\Ticket\TicketRepositoryInterface;
use StageArt\Domain\Ticket\TicketStatus;
use wpdb;

final class WordPressTicketRepository implements TicketRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_tickets';
    }

    public function save(Ticket $ticket): void
    {
        $row = [
            'production_id' => $ticket->productionId()->toString(),
            'name' => $ticket->name(),
            'price' => $ticket->price(),
            'remarks' => $ticket->remarks(),
            'status' => $ticket->status()->toString(),
            'updated_at' => $ticket->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $ticket->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $ticket->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update Ticket.');
            }

            return;
        }

        $row['id'] = $ticket->id()->toString();
        $row['created_at'] = $ticket->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert Ticket.');
        }
    }

    public function findById(TicketId $id): ?Ticket
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE production_id = %s ORDER BY created_at ASC", $productionId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $values = array_map(static fn (TicketId $id): string => $id->toString(), $ids);
        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id IN ({$placeholders})", $values),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Ticket
    {
        return Ticket::reconstitute(
            TicketId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            $row['name'],
            (int) $row['price'],
            $row['remarks'],
            TicketStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
