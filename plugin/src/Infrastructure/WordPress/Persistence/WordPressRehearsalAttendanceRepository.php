<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus;
use wpdb;

/**
 * save() explicitly checks wpdb insert/update return values and throws on
 * failure (unlike WordPressProductionRepository/WordPressParticipantRepository's
 * silent-on-failure precedent): ConfirmRehearsalUseCase relies on a
 * RehearsalAttendance save failure actually throwing so that
 * WordPressTransactionManager's catch(Throwable) rolls back the paired
 * Rehearsal::confirm() status change - Phase 2 instruction §9's "partial
 * failure must not leave Rehearsal CONFIRMED without complete Phase 2
 * generation" requirement depends on this.
 */
final class WordPressRehearsalAttendanceRepository implements RehearsalAttendanceRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_rehearsal_attendances';
    }

    public function save(RehearsalAttendance $attendance): void
    {
        $row = [
            'rehearsal_id' => $attendance->rehearsalId()->toString(),
            'person_id' => $attendance->personId()->toString(),
            'phase' => $attendance->phase()->toString(),
            'status' => $attendance->status()->toString(),
            'updated_at' => $attendance->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $attendance->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $attendance->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update RehearsalAttendance.');
            }

            return;
        }

        $row['id'] = $attendance->id()->toString();
        $row['created_at'] = $attendance->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert RehearsalAttendance (duplicate RehearsalId+PersonId+Phase?).');
        }
    }

    public function findById(RehearsalAttendanceId $id): ?RehearsalAttendance
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRehearsalIdAndPhase(RehearsalId $rehearsalId, RehearsalAttendancePhase $phase): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE rehearsal_id = %s AND phase = %s",
                $rehearsalId->toString(),
                $phase->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByRehearsalIdAndPersonIdAndPhase(
        RehearsalId $rehearsalId,
        PersonId $personId,
        RehearsalAttendancePhase $phase
    ): ?RehearsalAttendance {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE rehearsal_id = %s AND person_id = %s AND phase = %s",
                $rehearsalId->toString(),
                $personId->toString(),
                $phase->toString()
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findUpcomingByPersonId(
        PersonId $personId,
        DateTimeImmutable $from,
        array $excludedRehearsalStatuses,
        int $limit
    ): array {
        $rehearsalsTable = $this->wpdb->prefix . 'stageart_rehearsals';

        $statusPlaceholders = implode(', ', array_fill(0, count($excludedRehearsalStatuses), '%s'));
        $statusClause = $excludedRehearsalStatuses === []
            ? ''
            : "AND r.status NOT IN ({$statusPlaceholders})";

        $params = array_merge(
            [$personId->toString(), $from->format('Y-m-d H:i:s')],
            $excludedRehearsalStatuses,
            [$limit]
        );

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT ra.* FROM {$this->table} ra "
                . "INNER JOIN {$rehearsalsTable} r ON r.id = ra.rehearsal_id "
                . "WHERE ra.person_id = %s AND r.start_date_time >= %s {$statusClause} "
                . "ORDER BY r.start_date_time ASC "
                . "LIMIT %d",
                $params
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): RehearsalAttendance
    {
        return RehearsalAttendance::reconstitute(
            RehearsalAttendanceId::fromString($row['id']),
            RehearsalId::fromString($row['rehearsal_id']),
            PersonId::fromString($row['person_id']),
            RehearsalAttendancePhase::fromString($row['phase']),
            RehearsalAttendanceStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
