<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Domain\UserAccount\UserAccountId;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountStatus;
use wpdb;

final class WordPressUserAccountRepository implements UserAccountRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_user_accounts';
    }

    public function save(UserAccount $userAccount): void
    {
        $row = [
            'person_id' => $userAccount->personId()->toString(),
            'status' => $userAccount->status()->toString(),
            'updated_at' => $userAccount->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $userAccount->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $userAccount->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            return;
        }

        $row['id'] = $userAccount->id()->toString();
        $row['created_at'] = $userAccount->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function findById(UserAccountId $id): ?UserAccount
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPersonId(PersonId $personId): ?UserAccount
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE person_id = %s", $personId->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): UserAccount
    {
        return UserAccount::reconstitute(
            UserAccountId::fromString($row['id']),
            PersonId::fromString($row['person_id']),
            UserAccountStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
