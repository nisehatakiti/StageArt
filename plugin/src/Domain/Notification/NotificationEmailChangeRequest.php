<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;

/**
 * 通知用Email確認・変更機能: a single-use, hash-only-stored Token
 * artifact confirming a Person's candidate NEW notification email
 * before it is allowed to replace their `NotificationEmail` - the same
 * shape/security properties as `EmailVerificationToken`
 * (`isConsumed()`/`isExpired()`/`isUsable()`, hash-only storage,
 * single-use), but deliberately its own Entity rather than reusing that
 * one: `EmailVerificationToken` verifies whatever email is already
 * fixed on `EmailCredential` (UserAccount-keyed, no candidate email of
 * its own); this Token instead CARRIES the candidate email itself and
 * is Person-keyed, matching `NotificationEmail`'s own key - the two
 * purposes must stay non-interchangeable, exactly per
 * `EmailVerificationToken`'s own docblock reasoning for staying separate
 * from `PasswordResetToken`.
 *
 * One row per Person (see NotificationEmailChangeRequestRepositoryInterface),
 * the same "lazily created, upsert-by-person" shape as `NotificationEmail`/
 * `PushPreference`. A brand new change request always REPLACES any
 * previous one for that Person via `replaceWith()` (same id, new
 * candidate email/token hash/expiry, `consumedAt` reset to null) - this
 * is what makes an old, superseded verification link stop working the
 * instant a newer one is requested (仕様書 §9): the old token's hash no
 * longer matches any stored row once replaced.
 */
final class NotificationEmailChangeRequest
{
    private NotificationEmailChangeRequestId $id;
    private PersonId $personId;
    private string $candidateEmail;
    private string $tokenHash;
    private DateTimeImmutable $expiresAt;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $consumedAt;

    private function __construct(
        NotificationEmailChangeRequestId $id,
        PersonId $personId,
        string $candidateEmail,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $consumedAt
    ) {
        $this->id = $id;
        $this->personId = $personId;
        $this->candidateEmail = $candidateEmail;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
        $this->consumedAt = $consumedAt;
    }

    public static function create(
        PersonId $personId,
        string $candidateEmail,
        string $tokenHash,
        DateTimeImmutable $expiresAt
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            NotificationEmailChangeRequestId::generate(),
            $personId,
            self::validateEmail($candidateEmail),
            $tokenHash,
            $expiresAt,
            $now,
            null
        );
    }

    public static function reconstitute(
        NotificationEmailChangeRequestId $id,
        PersonId $personId,
        string $candidateEmail,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $consumedAt
    ): self {
        return new self($id, $personId, $candidateEmail, $tokenHash, $expiresAt, $createdAt, $consumedAt);
    }

    /**
     * Supersedes this row in place (same id/personId/createdAt) with a
     * brand new candidate email/token/expiry, and clears any prior
     * consumedAt - a fresh request is always usable again regardless of
     * whether the previous one had been consumed or had expired.
     */
    public function replaceWith(string $candidateEmail, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        $this->candidateEmail = self::validateEmail($candidateEmail);
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->consumedAt = null;
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

    public function id(): NotificationEmailChangeRequestId
    {
        return $this->id;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function candidateEmail(): string
    {
        return $this->candidateEmail;
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

    private static function validateEmail(string $email): string
    {
        $trimmed = trim($email);

        if ($trimmed === '' || filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email address: {$email}");
        }

        return $trimmed;
    }
}
