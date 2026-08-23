<?php

declare(strict_types=1);

namespace StageArt\Domain\Authentication;

use DateTimeImmutable;
use StageArt\Domain\UserAccount\UserAccountId;

/**
 * A single-use Token artifact (not an Identity - see RefreshToken's own
 * docblock for the same reasoning), issued by RequestPasswordResetUseCase
 * and consumed by ResetPasswordUseCase. Only the SHA-256 hash of the
 * opaque token value is ever held here, matching RefreshToken's "平文で
 * 保存しない" handling. Unlike RefreshToken (which can be used repeatedly
 * until revoked/expired), this Token is consumed exactly once on a
 * successful reset - consume() rather than revoke(), to make that
 * single-use semantic explicit at the call site.
 */
final class PasswordResetToken
{
    private PasswordResetTokenId $id;
    private UserAccountId $userAccountId;
    private string $tokenHash;
    private DateTimeImmutable $expiresAt;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $consumedAt;

    private function __construct(
        PasswordResetTokenId $id,
        UserAccountId $userAccountId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $consumedAt
    ) {
        $this->id = $id;
        $this->userAccountId = $userAccountId;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
        $this->consumedAt = $consumedAt;
    }

    public static function create(UserAccountId $userAccountId, string $tokenHash, DateTimeImmutable $expiresAt): self
    {
        return new self(PasswordResetTokenId::generate(), $userAccountId, $tokenHash, $expiresAt, new DateTimeImmutable(), null);
    }

    public static function reconstitute(
        PasswordResetTokenId $id,
        UserAccountId $userAccountId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $consumedAt
    ): self {
        return new self($id, $userAccountId, $tokenHash, $expiresAt, $createdAt, $consumedAt);
    }

    public function consume(): void
    {
        if ($this->consumedAt === null) {
            $this->consumedAt = new DateTimeImmutable();
        }
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isUsable(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired();
    }

    public function id(): PasswordResetTokenId
    {
        return $this->id;
    }

    public function userAccountId(): UserAccountId
    {
        return $this->userAccountId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function consumedAt(): ?DateTimeImmutable
    {
        return $this->consumedAt;
    }
}
