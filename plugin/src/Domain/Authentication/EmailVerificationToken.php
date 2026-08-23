<?php

declare(strict_types=1);

namespace StageArt\Domain\Authentication;

use DateTimeImmutable;
use StageArt\Domain\UserAccount\UserAccountId;

/**
 * A single-use Token artifact for confirming EmailCredential.email, the
 * same shape as PasswordResetToken (see its docblock for the shared
 * reasoning: not an Identity, hash-only storage, consume-once semantics).
 * Kept as its own Entity rather than sharing PasswordResetToken - the two
 * purposes must never be interchangeable (a leaked password-reset link
 * must not also be usable to mark an email verified, and vice versa).
 */
final class EmailVerificationToken
{
    private EmailVerificationTokenId $id;
    private UserAccountId $userAccountId;
    private string $tokenHash;
    private DateTimeImmutable $expiresAt;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $consumedAt;

    private function __construct(
        EmailVerificationTokenId $id,
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
        return new self(EmailVerificationTokenId::generate(), $userAccountId, $tokenHash, $expiresAt, new DateTimeImmutable(), null);
    }

    public static function reconstitute(
        EmailVerificationTokenId $id,
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

    public function id(): EmailVerificationTokenId
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
