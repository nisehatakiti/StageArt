<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use StageArt\Application\CheckIn\WalkUpDuplicateRequestException;
use StageArt\Application\CheckIn\WalkUpIdempotencyStoreInterface;
use StageArt\Domain\Reservation\ReservationId;
use wpdb;

final class WordPressWalkUpIdempotencyStore implements WalkUpIdempotencyStoreInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_walkup_idempotency_keys';
    }

    public function findReservationId(string $idempotencyKey): ?ReservationId
    {
        $reservationId = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT reservation_id FROM {$this->table} WHERE idempotency_key = %s", $idempotencyKey)
        );

        return $reservationId ? ReservationId::fromString($reservationId) : null;
    }

    public function record(string $idempotencyKey, ReservationId $reservationId): void
    {
        $result = $this->wpdb->insert($this->table, [
            'idempotency_key' => $idempotencyKey,
            'reservation_id' => $reservationId->toString(),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            // This table's only constraint is the UNIQUE key on
            // idempotency_key - any insert failure here means a
            // concurrent request already recorded this exact key first.
            throw new WalkUpDuplicateRequestException();
        }
    }
}
