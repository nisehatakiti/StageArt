<?php

declare(strict_types=1);

namespace StageArt\Domain\Authentication;

use DateTimeImmutable;
use StageArt\Domain\UserAccount\UserAccountId;

/**
 * Phase 2 (StageArt Authentication): a Session/Token artifact, not an
 * Identity - UserAccount.md's own "# Session" section anticipates this
 * as separate management from UserAccount itself ("Session自体をPerson
 * やOrganizationのBusiness Dataとして扱わない"). Only the SHA-256 hash
 * of the opaque token value is ever held here - the plain token exists
 * only transiently in the Infrastructure layer at issuance time and in
 * the client's own storage, per UserAccount.md/Credential.md's "平文で
 * 保存しない" principle. This Entity has no knowledge of JWT, hashing
 * algorithms, or any other Infrastructure detail - it only tracks the
 * hash string, expiry, and revocation state.
 */
final class RefreshToken
{
    private RefreshTokenId $id;
    private UserAccountId $userAccountId;
    private string $tokenHash;
    private DateTimeImmutable $expiresAt;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $revokedAt;

    private function __construct(
        RefreshTokenId $id,
        UserAccountId $userAccountId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $revokedAt
    ) {
        $this->id = $id;
        $this->userAccountId = $userAccountId;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
        $this->revokedAt = $revokedAt;
    }

    public static function create(UserAccountId $userAccountId, string $tokenHash, DateTimeImmutable $expiresAt): self
    {
        return new self(RefreshTokenId::generate(), $userAccountId, $tokenHash, $expiresAt, new DateTimeImmutable(), null);
    }

    public static function reconstitute(
        RefreshTokenId $id,
        UserAccountId $userAccountId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $revokedAt
    ): self {
        return new self($id, $userAccountId, $tokenHash, $expiresAt, $createdAt, $revokedAt);
    }

    public function revoke(): void
    {
        if ($this->revokedAt === null) {
            $this->revokedAt = new DateTimeImmutable();
        }
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * The single check a caller needs before honoring this token for a
     * refresh: not revoked and not expired.
     */
    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function id(): RefreshTokenId
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

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }
}
