<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Authentication\RefreshToken;
use StageArt\Domain\Authentication\RefreshTokenId;
use StageArt\Domain\Authentication\RefreshTokenRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;
use wpdb;

final class WordPressRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_refresh_tokens';
    }

    public function save(RefreshToken $refreshToken): void
    {
        $row = [
            'user_account_id' => $refreshToken->userAccountId()->toString(),
            'token_hash' => $refreshToken->tokenHash(),
            'expires_at' => $refreshToken->expiresAt()->format('Y-m-d H:i:s'),
            'revoked_at' => $refreshToken->revokedAt() !== null ? $refreshToken->revokedAt()->format('Y-m-d H:i:s') : null,
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $refreshToken->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $refreshToken->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            return;
        }

        $row['id'] = $refreshToken->id()->toString();
        $row['created_at'] = $refreshToken->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function findByTokenHash(string $tokenHash): ?RefreshToken
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

    private function hydrate(array $row): RefreshToken
    {
        return RefreshToken::reconstitute(
            RefreshTokenId::fromString($row['id']),
            UserAccountId::fromString($row['user_account_id']),
            $row['token_hash'],
            new DateTimeImmutable($row['expires_at']),
            new DateTimeImmutable($row['created_at']),
            $row['revoked_at'] !== null ? new DateTimeImmutable($row['revoked_at']) : null
        );
    }
}
