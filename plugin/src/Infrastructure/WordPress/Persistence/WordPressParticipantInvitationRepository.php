<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use wpdb;

/**
 * PR #7 R14-B (schema 1.49.0, design doc §5.1 / §6.1 / §7.4):
 *
 * - The invitation's response (invitee_person_id, responded_at,
 *   participant_id), its past invalidation (close_reason, closed_at,
 *   closed_by_person_id) and resend_count are stored and read back as the
 *   Domain holds them; ACCEPTED / REJECTED are plain status values. A
 *   pre-R14-B row - CONSUMED, CANCELLED without a close reason, PENDING
 *   with its 24-hour expiry - reads back unchanged, with NULLs and
 *   resend_count 0. Nothing is derived or changed here.
 * - token_hash is written as the Domain holds it: this class never makes
 *   or changes one (a resend() keeps it).
 * - revocation_notified_at, current_send_id, last_sent_at and updated_at
 *   (schema 1.49.0) belong to the send / notification steps that do not
 *   exist yet: they are neither written nor read here, so an INSERT leaves
 *   them NULL and an UPDATE leaves whatever they hold.
 * - A failed write throws (WordPressCheckedWrites): UNIQUE key violation
 *   (1062, e.g. token_hash) as UniqueConstraintViolationException, other
 *   failures as PersistenceException. The message never carries the
 *   database's error text, which can quote the e-mail address or token
 *   hash (before R14-B it did).
 * - Date-times are stored and read as UTC, like WordPressParticipantRepository.
 */
final class WordPressParticipantInvitationRepository implements ParticipantInvitationRepositoryInterface
{
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_participant_invitations';
    }

    public function save(ParticipantInvitation $invitation): void
    {
        $row = [
            'production_id' => $invitation->productionId()->toString(),
            'email' => $invitation->email(),
            'invited_name' => $invitation->invitedName(),
            'invited_by_person_id' => $invitation->invitedByPersonId()->toString(),
            'participant_type' => $invitation->participantType()->toString(),
            'remarks' => $invitation->remarks(),
            'token_hash' => $invitation->tokenHash(),
            'status' => $invitation->status()->toString(),
            'expires_at' => self::toDb($invitation->expiresAt()),
            'consumed_at' => self::toDbOrNull($invitation->consumedAt()),
            'invitee_person_id' => $invitation->inviteePersonId() !== null ? $invitation->inviteePersonId()->toString() : null,
            'responded_at' => self::toDbOrNull($invitation->respondedAt()),
            'participant_id' => $invitation->participantId() !== null ? $invitation->participantId()->toString() : null,
            'close_reason' => $invitation->closeReason() !== null ? $invitation->closeReason()->toString() : null,
            'closed_at' => self::toDbOrNull($invitation->closedAt()),
            'closed_by_person_id' => $invitation->closedByPersonId() !== null ? $invitation->closedByPersonId()->toString() : null,
            'resend_count' => $invitation->resendCount(),
        ];

        $id = $invitation->id()->toString();

        if (WordPressCheckedWrites::existingId($this->wpdb, $this->table, $id) !== null) {
            WordPressCheckedWrites::update($this->wpdb, $this->table, $row, ['id' => $id]);

            return;
        }

        $row['id'] = $id;
        $row['created_at'] = self::toDb($invitation->createdAt());

        WordPressCheckedWrites::insert($this->wpdb, $this->table, $row);
    }

    public function findById(ParticipantInvitationId $id): ?ParticipantInvitation
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTokenHash(string $tokenHash): ?ParticipantInvitation
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE token_hash = %s", $tokenHash),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionEmailAndType(
        ProductionId $productionId,
        string $email,
        ParticipantType $participantType
    ): array {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s AND email = %s AND participant_type = %s",
                $productionId->toString(),
                $email,
                $participantType->toString()
            ),
            ARRAY_A
        );

        return array_map(fn (array $row): ParticipantInvitation => $this->hydrate($row), $rows ?: []);
    }

    public function findByEmail(string $email): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE email = %s", $email),
            ARRAY_A
        );

        return array_map(fn (array $row): ParticipantInvitation => $this->hydrate($row), $rows ?: []);
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE production_id = %s", $productionId->toString()),
            ARRAY_A
        );

        return array_map(fn (array $row): ParticipantInvitation => $this->hydrate($row), $rows ?: []);
    }

    private function hydrate(array $row): ParticipantInvitation
    {
        return ParticipantInvitation::reconstitute(
            ParticipantInvitationId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            $row['email'],
            $row['invited_name'],
            PersonId::fromString($row['invited_by_person_id']),
            ParticipantType::fromString($row['participant_type']),
            $row['remarks'],
            $row['token_hash'],
            ParticipantInvitationStatus::fromString($row['status']),
            self::fromDb($row['created_at']),
            self::fromDb($row['expires_at']),
            isset($row['consumed_at']) ? self::fromDb($row['consumed_at']) : null,
            isset($row['invitee_person_id']) ? PersonId::fromString($row['invitee_person_id']) : null,
            isset($row['responded_at']) ? self::fromDb($row['responded_at']) : null,
            isset($row['participant_id']) ? ParticipantId::fromString($row['participant_id']) : null,
            isset($row['close_reason']) ? ParticipantInvitationCloseReason::fromString($row['close_reason']) : null,
            isset($row['closed_at']) ? self::fromDb($row['closed_at']) : null,
            isset($row['closed_by_person_id']) ? PersonId::fromString($row['closed_by_person_id']) : null,
            isset($row['resend_count']) ? (int) $row['resend_count'] : 0
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
