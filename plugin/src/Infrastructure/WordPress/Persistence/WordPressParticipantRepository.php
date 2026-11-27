<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantConsentSource;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionId;
use wpdb;

/**
 * PR #7 R14-B (schema 1.49.0, design doc §4.5 / §6.1 / §7.4):
 *
 * - enrollment_source, consent_source, consented_at and requested_at are
 *   stored and read back exactly as the Participant holds them. NULL stays
 *   NULL both ways (pre-R14-B rows: no route, no recorded consent, no
 *   R14-B request); nothing is filled in here, and created_at is never
 *   used for requested_at.
 * - A failed write throws (WordPressCheckedWrites): a UNIQUE key violation
 *   - the (production, subject, type) key - as
 *   UniqueConstraintViolationException, any other failure as
 *   PersistenceException; a failed existence read too, so it never turns
 *   into an INSERT. Before R14-B both write results were ignored (U13:
 *   201 with no row stored).
 * - findByIdForUpdate() / findByProductionAndSubjectForUpdate() (stage 3)
 *   lock the row with SELECT ... FOR UPDATE inside the caller's
 *   transaction (after the Production lock, §5.4) and throw on a failed
 *   read, so a failure is never taken for "no such row" (which would
 *   create a second row).
 * - Date-times are stored as UTC wall-clock values - what every existing
 *   row already holds, since WordPress fixes PHP's default time zone to
 *   UTC - and read back as UTC. A value given in another zone (e.g. +09:00)
 *   is converted, not stored with its own digits.
 */
final class WordPressParticipantRepository implements ParticipantRepositoryInterface
{
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_participants';
    }

    public function save(Participant $participant): void
    {
        $row = [
            'production_id' => $participant->productionId()->toString(),
            'subject_type' => $participant->subjectType()->toString(),
            'subject_id' => $participant->subjectId(),
            'participant_type' => $participant->participantType()->toString(),
            'status' => $participant->status()->toString(),
            'remarks' => $participant->remarks(),
            'display_name' => $participant->displayName(),
            'updated_at' => self::toDb($participant->updatedAt()),
            'enrollment_source' => $participant->enrollmentSource() !== null ? $participant->enrollmentSource()->toString() : null,
            'consent_source' => $participant->consentSource() !== null ? $participant->consentSource()->toString() : null,
            'consented_at' => self::toDbOrNull($participant->consentedAt()),
            'requested_at' => self::toDbOrNull($participant->requestedAt()),
        ];

        $id = $participant->id()->toString();

        if (WordPressCheckedWrites::existingId($this->wpdb, $this->table, $id) !== null) {
            WordPressCheckedWrites::update($this->wpdb, $this->table, $row, ['id' => $id]);

            return;
        }

        $row['id'] = $id;
        $row['created_at'] = self::toDb($participant->createdAt());

        WordPressCheckedWrites::insert($this->wpdb, $this->table, $row);
    }

    public function findById(ParticipantId $id): ?Participant
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByIdForUpdate(ParticipantId $id): ?Participant
    {
        $this->wpdb->last_error = '';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s FOR UPDATE", $id->toString()),
            ARRAY_A
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionAndSubjectForUpdate(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): ?Participant {
        $this->wpdb->last_error = '';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} "
                . "WHERE production_id = %s AND subject_type = %s AND subject_id = %s AND participant_type = %s FOR UPDATE",
                $productionId->toString(),
                $subjectType->toString(),
                $subjectId,
                $participantType->toString()
            ),
            ARRAY_A
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * R14-B §4.6: a failed read throws (assertRead) - $wpdb answers a
     * failed get_col() with an empty array, which would read as "no ACTIVE
     * row of another type" and release delegate roles that must be kept.
     */
    public function existsActivePersonParticipantOfAnotherTypeForUpdate(
        ProductionId $productionId,
        string $personId,
        ParticipantType $participantType
    ): bool {
        $this->wpdb->last_error = '';
        $ids = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table} "
                . "WHERE production_id = %s AND subject_type = %s AND subject_id = %s "
                . "AND participant_type <> %s AND status = %s ORDER BY id FOR UPDATE",
                $productionId->toString(),
                ParticipantSubjectType::PERSON,
                $personId,
                $participantType->toString(),
                ParticipantStatus::ACTIVE
            )
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        return is_array($ids) && $ids !== [];
    }

    /**
     * R14-B §4.6 / §7.4 (stage 4): one indexed read, no FOR UPDATE (a
     * permission decision). A failed read throws (assertRead) instead of
     * answering "no" or "yes".
     */
    public function existsActivePersonParticipant(ProductionId $productionId, string $personId): bool
    {
        $this->wpdb->last_error = '';
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table} "
                . "WHERE production_id = %s AND subject_type = %s AND subject_id = %s AND status = %s "
                . "LIMIT 1",
                $productionId->toString(),
                ParticipantSubjectType::PERSON,
                $personId,
                ParticipantStatus::ACTIVE
            )
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        return $found !== null && $found !== '';
    }

    public function findPersonParticipants(ProductionId $productionId, string $personId): array
    {
        return $this->personRows($productionId, $personId, '');
    }

    public function findPersonParticipantsForUpdate(ProductionId $productionId, string $personId): array
    {
        return $this->personRows($productionId, $personId, ' FOR UPDATE');
    }

    /**
     * @return Participant[]
     */
    private function personRows(ProductionId $productionId, string $personId, string $lock): array
    {
        $this->wpdb->last_error = '';
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s AND subject_type = %s AND subject_id = %s ORDER BY id" . $lock,
                $productionId->toString(),
                ParticipantSubjectType::PERSON,
                $personId
            ),
            ARRAY_A
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        return array_map([$this, 'hydrate'], is_array($rows) ? $rows : []);
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE production_id = %s", $productionId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByProductionAndSubject(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): ?Participant {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} "
                . "WHERE production_id = %s AND subject_type = %s AND subject_id = %s AND participant_type = %s",
                $productionId->toString(),
                $subjectType->toString(),
                $subjectId,
                $participantType->toString()
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySubject(ParticipantSubjectType $subjectType, string $subjectId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE subject_type = %s AND subject_id = %s",
                $subjectType->toString(),
                $subjectId
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Participant
    {
        return Participant::reconstitute(
            ParticipantId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            ParticipantSubjectType::fromString($row['subject_type']),
            $row['subject_id'],
            ParticipantType::fromString($row['participant_type']),
            ParticipantStatus::fromString($row['status']),
            self::fromDb($row['created_at']),
            self::fromDb($row['updated_at']),
            $row['remarks'] ?? null,
            $row['display_name'] ?? null,
            isset($row['enrollment_source']) ? ParticipantEnrollmentSource::fromString($row['enrollment_source']) : null,
            isset($row['consent_source']) ? ParticipantConsentSource::fromString($row['consent_source']) : null,
            isset($row['consented_at']) ? self::fromDb($row['consented_at']) : null,
            isset($row['requested_at']) ? self::fromDb($row['requested_at']) : null
        );
    }

    private static function toDb(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format(self::DATE_TIME_FORMAT);
    }

    private static function toDbOrNull(?DateTimeImmutable $value): ?string
    {
        return $value !== null ? self::toDb($value) : null;
    }

    private static function fromDb(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
