<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Notification\NotificationEmailChangeRequest;
use StageArt\Domain\Notification\NotificationEmailChangeRequestId;
use StageArt\Domain\Notification\NotificationEmailChangeRequestRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use wpdb;

final class WordPressNotificationEmailChangeRequestRepository implements NotificationEmailChangeRequestRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_notification_email_change_requests';
    }

    public function save(NotificationEmailChangeRequest $request): void
    {
        $row = [
            'person_id' => $request->personId()->toString(),
            'candidate_email' => $request->candidateEmail(),
            'token_hash' => $request->tokenHash(),
            'expires_at' => $request->expiresAt()->format('Y-m-d H:i:s'),
            'consumed_at' => $request->consumedAt()?->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $request->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $request->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update NotificationEmailChangeRequest.');
            }

            return;
        }

        $row['id'] = $request->id()->toString();
        $row['created_at'] = $request->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert NotificationEmailChangeRequest.');
        }
    }

    public function findByPersonId(PersonId $personId): ?NotificationEmailChangeRequest
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE person_id = %s", $personId->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTokenHash(string $tokenHash): ?NotificationEmailChangeRequest
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE token_hash = %s", $tokenHash),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): NotificationEmailChangeRequest
    {
        return NotificationEmailChangeRequest::reconstitute(
            NotificationEmailChangeRequestId::fromString($row['id']),
            PersonId::fromString($row['person_id']),
            $row['candidate_email'],
            $row['token_hash'],
            new DateTimeImmutable($row['expires_at']),
            new DateTimeImmutable($row['created_at']),
            $row['consumed_at'] !== null ? new DateTimeImmutable($row['consumed_at']) : null
        );
    }
}
