<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Authentication\EmailVerificationToken;
use StageArt\Domain\Authentication\EmailVerificationTokenId;
use StageArt\Domain\Authentication\EmailVerificationTokenRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;
use wpdb;

final class WordPressEmailVerificationTokenRepository implements EmailVerificationTokenRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_email_verification_tokens';
    }

    public function save(EmailVerificationToken $token): void
    {
        $row = [
            'user_account_id' => $token->userAccountId()->toString(),
            'token_hash' => $token->tokenHash(),
            'expires_at' => $token->expiresAt()->format('Y-m-d H:i:s'),
            'consumed_at' => $token->consumedAt() !== null ? $token->consumedAt()->format('Y-m-d H:i:s') : null,
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $token->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $token->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            return;
        }

        $row['id'] = $token->id()->toString();
        $row['created_at'] = $token->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function findByTokenHash(string $tokenHash): ?EmailVerificationToken
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE token_hash = %s", $tokenHash),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUserAccountId(UserAccountId $userAccountId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE user_account_id = %s", $userAccountId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): EmailVerificationToken
    {
        return EmailVerificationToken::reconstitute(
            EmailVerificationTokenId::fromString($row['id']),
            UserAccountId::fromString($row['user_account_id']),
            $row['token_hash'],
            new DateTimeImmutable($row['expires_at']),
            new DateTimeImmutable($row['created_at']),
            $row['consumed_at'] !== null ? new DateTimeImmutable($row['consumed_at']) : null
        );
    }
}
