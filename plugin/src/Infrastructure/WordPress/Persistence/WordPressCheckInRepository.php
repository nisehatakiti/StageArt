<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\CheckIn\CheckIn;
use StageArt\Domain\CheckIn\CheckInId;
use StageArt\Domain\CheckIn\CheckInRepositoryInterface;
use StageArt\Domain\CheckIn\CheckInStatus;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Reservation\ReservationId;
use wpdb;

final class WordPressCheckInRepository implements CheckInRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_check_ins';
    }

    public function save(CheckIn $checkIn): void
    {
        $row = [
            'reservation_id' => $checkIn->reservationId()->toString(),
            'performance_id' => $checkIn->performanceId()->toString(),
            'status' => $checkIn->status()->toString(),
            'checked_in_by' => $checkIn->checkedInBy()->toString(),
            'checked_in_at' => $checkIn->checkedInAt()->format('Y-m-d H:i:s'),
            'reversed_by' => $checkIn->reversedBy()?->toString(),
            'reversed_at' => $checkIn->reversedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $checkIn->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $checkIn->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $checkIn->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update CheckIn.');
            }

            return;
        }

        $row['id'] = $checkIn->id()->toString();
        $row['created_at'] = $checkIn->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert CheckIn.');
        }
    }

    public function findById(CheckInId $id): ?CheckIn
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findLatestByReservationId(ReservationId $reservationId): ?CheckIn
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE reservation_id = %s ORDER BY created_at DESC LIMIT 1",
                $reservationId->toString()
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPerformanceId(PerformanceId $performanceId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE performance_id = %s ORDER BY checked_in_at ASC", $performanceId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): CheckIn
    {
        return CheckIn::reconstitute(
            CheckInId::fromString($row['id']),
            ReservationId::fromString($row['reservation_id']),
            PerformanceId::fromString($row['performance_id']),
            CheckInStatus::fromString($row['status']),
            PersonId::fromString($row['checked_in_by']),
            new DateTimeImmutable($row['checked_in_at']),
            ! empty($row['reversed_by']) ? PersonId::fromString($row['reversed_by']) : null,
            ! empty($row['reversed_at']) ? new DateTimeImmutable($row['reversed_at']) : null,
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
