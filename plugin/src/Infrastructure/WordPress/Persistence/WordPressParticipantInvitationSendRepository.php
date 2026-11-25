<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSendRecord;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSendRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use wpdb;

/**
 * PR #7 R14-B stage 4 (design doc §6.2 / §6.7 / §11.3):
 * stageart_participant_invitation_sends (schema 1.49.0).
 *
 * - add(): one INSERT of a PENDING attempt, checked.
 * - keyVersionsSince() / requestedAtsForKeysSince(): the two §6.7 reads
 *   (KEY production_requested_version, KEY
 *   production_version_email_requested); last_error is cleared first and a
 *   failed read throws - a failure never reads as "no sends".
 * - completeIfPending(): UPDATE ... WHERE id = ? AND status = 'PENDING'; the
 *   affected-row count says whether this call recorded the result.
 * - recordsSince() / recordsByIds() (R14B-4R, §7.1 managers' list): one
 *   read each for the whole list - the Production's sends in the 30-day
 *   window (KEY production_requested_version range) and the current
 *   attempts by primary key - checked like the other reads.
 * - The e-mail address is never stored or read here (only email_key, an
 *   HMAC); no key value is ever logged. Date-times are UTC.
 */
final class WordPressParticipantInvitationSendRepository implements ParticipantInvitationSendRepositoryInterface
{
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_participant_invitation_sends';
    }

    public function add(ParticipantInvitationSend $send): void
    {
        WordPressCheckedWrites::insert($this->wpdb, $this->table, [
            'id' => $send->id(),
            'invitation_id' => $send->invitationId()->toString(),
            'production_id' => $send->productionId()->toString(),
            'email_key' => $send->emailKey(),
            'email_key_version' => $send->emailKeyVersion(),
            'kind' => $send->kind(),
            'status' => ParticipantInvitationSend::PENDING,
            'requested_by_person_id' => $send->requestedByPersonId()->toString(),
            'requested_at' => self::toDb($send->requestedAt()),
            'completed_at' => null,
        ]);
    }

    public function keyVersionsSince(ProductionId $productionId, DateTimeImmutable $since): array
    {
        $this->wpdb->last_error = '';
        $versions = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT email_key_version FROM {$this->table} WHERE production_id = %s AND requested_at >= %s",
                $productionId->toString(),
                self::toDb($since)
            )
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        return array_values(array_map('strval', is_array($versions) ? $versions : []));
    }

    public function requestedAtsForKeysSince(ProductionId $productionId, DateTimeImmutable $since, array $keysByVersion): array
    {
        if ($keysByVersion === []) {
            return [];
        }

        $pairs = [];
        $args = [$productionId->toString(), self::toDb($since)];

        foreach ($keysByVersion as $version => $key) {
            $pairs[] = '(email_key_version = %s AND email_key = %s)';
            $args[] = (string) $version;
            $args[] = $key;
        }

        $this->wpdb->last_error = '';
        $rows = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT requested_at FROM {$this->table} WHERE production_id = %s AND requested_at >= %s AND ("
                . implode(' OR ', $pairs)
                . ') ORDER BY requested_at ASC',
                ...$args
            )
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        return array_map(
            static fn ($value): DateTimeImmutable => new DateTimeImmutable((string) $value, new DateTimeZone('UTC')),
            is_array($rows) ? $rows : []
        );
    }

    public function completeIfPending(string $sendId, string $status, DateTimeImmutable $completedAt): bool
    {
        if (! ParticipantInvitationSend::isResult($status)) {
            throw new InvalidArgumentException("Invalid send result: {$status}");
        }

        $affected = WordPressCheckedWrites::query(
            $this->wpdb,
            'update',
            $this->table,
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET status = %s, completed_at = %s WHERE id = %s AND status = %s",
                $status,
                self::toDb($completedAt),
                $sendId,
                ParticipantInvitationSend::PENDING
            )
        );

        return $affected === 1;
    }

    public function recordsSince(ProductionId $productionId, DateTimeImmutable $since): array
    {
        $this->wpdb->last_error = '';
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, email_key_version, email_key, status, requested_at FROM {$this->table}"
                . ' WHERE production_id = %s AND requested_at >= %s ORDER BY requested_at ASC, id ASC',
                $productionId->toString(),
                self::toDb($since)
            ),
            ARRAY_A
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        return array_map([self::class, 'hydrateRecord'], is_array($rows) ? $rows : []);
    }

    public function recordsByIds(ProductionId $productionId, array $sendIds): array
    {
        $sendIds = array_values(array_unique(array_map('strval', $sendIds)));

        if ($sendIds === []) {
            return [];
        }

        $this->wpdb->last_error = '';
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, email_key_version, email_key, status, requested_at FROM {$this->table}"
                . ' WHERE production_id = %s AND id IN (' . implode(', ', array_fill(0, count($sendIds), '%s')) . ')',
                $productionId->toString(),
                ...$sendIds
            ),
            ARRAY_A
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        $records = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $record = self::hydrateRecord($row);
            $records[$record->id()] = $record;
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrateRecord(array $row): ParticipantInvitationSendRecord
    {
        return new ParticipantInvitationSendRecord(
            (string) $row['id'],
            (string) $row['email_key_version'],
            (string) $row['email_key'],
            (string) $row['status'],
            new DateTimeImmutable((string) $row['requested_at'], new DateTimeZone('UTC'))
        );
    }

    private static function toDb(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format(self::DATE_TIME_FORMAT);
    }
}
