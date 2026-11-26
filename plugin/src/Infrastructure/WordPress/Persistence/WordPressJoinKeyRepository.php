<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyId;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use wpdb;

final class WordPressJoinKeyRepository implements JoinKeyRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_join_keys';
    }

    public function save(JoinKey $joinKey): void
    {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $joinKey->id()->toString())
        );

        $row = [
            'code' => $joinKey->code(),
            'target_type' => $joinKey->targetType(),
            'target_id' => $joinKey->targetId(),
            'status' => $joinKey->status(),
            'issued_by_person_id' => $joinKey->issuedByPersonId()->toString(),
            'issued_at' => $joinKey->issuedAt()->format('Y-m-d H:i:s'),
            'expires_at' => $joinKey->expiresAt()?->format('Y-m-d H:i:s'),
            'max_uses' => $joinKey->maxUses(),
            'use_count' => $joinKey->useCount(),
            'disabled_at' => $joinKey->disabledAt()?->format('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $joinKey->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            return;
        }

        $row['id'] = $joinKey->id()->toString();

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    /**
     * R14-B (design doc §7.1, U13 C案): the use is counted by one
     * conditional UPDATE, so two requests can never both take the last use
     * and a request that is rolled back never leaves a use behind. The
     * status assignment comes first and reads the pre-increment use_count
     * (MySQL evaluates single-table SET assignments left to right), keeping
     * JoinKey::recordUse()'s EXHAUSTED-on-the-last-use rule. expires_at is
     * compared with the current UTC time, the form save() stores (PHP's
     * default time zone is UTC under WordPress).
     */
    public function recordUseIfUsable(JoinKeyId $id, DateTimeImmutable $now): bool
    {
        $changed = WordPressCheckedWrites::query($this->wpdb, 'update', $this->table, $this->wpdb->prepare(
            "UPDATE {$this->table} "
            . "SET status = CASE WHEN max_uses IS NOT NULL AND use_count + 1 >= max_uses THEN %s ELSE status END, "
            . "use_count = use_count + 1 "
            . "WHERE id = %s AND status = %s AND (max_uses IS NULL OR use_count < max_uses) "
            . "AND (expires_at IS NULL OR expires_at > %s)",
            JoinKey::STATUS_EXHAUSTED,
            $id->toString(),
            JoinKey::STATUS_ACTIVE,
            $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        ));

        return $changed === 1;
    }

    public function findById(JoinKeyId $id): ?JoinKey
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $normalizedCode): ?JoinKey
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE code = %s", $normalizedCode),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTarget(string $targetType, string $targetId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE target_type = %s AND target_id = %s ORDER BY issued_at DESC",
                $targetType,
                $targetId
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): JoinKey
    {
        return JoinKey::reconstitute(
            JoinKeyId::fromString($row['id']),
            $row['code'],
            $row['target_type'],
            $row['target_id'],
            $row['status'],
            PersonId::fromString($row['issued_by_person_id']),
            new DateTimeImmutable($row['issued_at']),
            $row['expires_at'] !== null ? new DateTimeImmutable($row['expires_at']) : null,
            $row['max_uses'] !== null ? (int) $row['max_uses'] : null,
            (int) $row['use_count'],
            $row['disabled_at'] !== null ? new DateTimeImmutable($row['disabled_at']) : null
        );
    }
}
