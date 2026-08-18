<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\EmailCredentialId;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;
use wpdb;

final class WordPressEmailCredentialRepository implements EmailCredentialRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_email_credentials';
    }

    public function save(EmailCredential $credential): void
    {
        $row = [
            'user_account_id' => $credential->userAccountId()->toString(),
            'email' => $credential->email(),
            'password_hash' => $credential->passwordHash(),
            'email_verified_at' => $credential->emailVerifiedAt() !== null
                ? $credential->emailVerifiedAt()->format('Y-m-d H:i:s')
                : null,
            'updated_at' => $credential->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $credential->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $credential->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            return;
        }

        $row['id'] = $credential->id()->toString();
        $row['created_at'] = $credential->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function findByUserAccountId(UserAccountId $userAccountId): ?EmailCredential
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE user_account_id = %s", $userAccountId->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?EmailCredential
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE email = %s", $email),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): EmailCredential
    {
        return EmailCredential::reconstitute(
            EmailCredentialId::fromString($row['id']),
            UserAccountId::fromString($row['user_account_id']),
            $row['email'],
            $row['password_hash'],
            $row['email_verified_at'] !== null ? new DateTimeImmutable($row['email_verified_at']) : null,
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
