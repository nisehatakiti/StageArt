<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;

/**
 * Google認証ユーザーのEmail通知先対応 phase: a Person's own StageArt-
 * managed notification destination email, deliberately separate from
 * both `EmailCredential` (the StageArt password-login email, keyed by
 * UserAccountId, not PersonId) and `ExternalIdentity` (OAuth linkage,
 * which never stores the provider's email at all - see that class's
 * own docblock and UserAccount.md's "emailをGoogle Identityの主キーに
 * しない"). `ExternalIdentity` must never depend on this Entity in
 * either direction - the two are related only through the one-time
 * seeding performed by `NotificationEmailSeeder` at Google
 * authentication time.
 *
 * One row per Person (see NotificationEmailRepositoryInterface), the
 * same "lazily created, not proactively backfilled" shape as
 * `PushPreference`. `source` records where the currently-stored email
 * came from (`'GOOGLE'` today - the only writer that exists yet); it is
 * bookkeeping only, never re-queried against the provider at
 * resolution or delivery time (`PersonEmailResolver`/
 * `WordPressEmailNotificationAdapter` never call back into Google).
 *
 * `verified` reflects the verification state *as observed at write
 * time* (Google's own `email_verified` claim, or - since 通知用Email確認
 * ・変更機能 - the Settings-UI change flow's own verification link).
 * `PersonEmailResolver` still checks it explicitly rather than assuming
 * every row is verified.
 *
 * 通知用Email確認・変更機能: `changeEmail()` is this Entity's only
 * mutator, called exactly once, by `VerifyNotificationEmailChangeUseCase`,
 * and only after its own `NotificationEmailChangeRequest` token has been
 * confirmed - never at change-request time (仕様書 §4's explicit
 * "Email入力時点ではNotificationEmailを変更しないでください"). It always
 * writes `verified = true` (unverified emails are never accepted this
 * way) and keeps the same id/personId/createdAt - a Settings-driven
 * change is a mutation of this Person's one existing notification
 * destination, not a new one.
 */
final class NotificationEmail
{
    public const SOURCE_GOOGLE = 'GOOGLE';
    public const SOURCE_USER = 'USER';

    private NotificationEmailId $id;
    private PersonId $personId;
    private string $email;
    private bool $verified;
    private string $source;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        NotificationEmailId $id,
        PersonId $personId,
        string $email,
        bool $verified,
        string $source,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->personId = $personId;
        $this->email = $email;
        $this->verified = $verified;
        $this->source = $source;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(PersonId $personId, string $email, bool $verified, string $source): self
    {
        $now = new DateTimeImmutable();

        return new self(
            NotificationEmailId::generate(),
            $personId,
            self::validateEmail($email),
            $verified,
            self::validateSource($source),
            $now,
            $now
        );
    }

    public static function reconstitute(
        NotificationEmailId $id,
        PersonId $personId,
        string $email,
        bool $verified,
        string $source,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $personId, $email, $verified, $source, $createdAt, $updatedAt);
    }

    /**
     * Replaces this Person's notification destination with a newly
     * user-verified email - always `verified = true` (a caller must
     * never invoke this with an unverified email; there is no parameter
     * to accidentally pass false for).
     */
    public function changeEmail(string $newEmail): void
    {
        $this->email = self::validateEmail($newEmail);
        $this->verified = true;
        $this->source = self::SOURCE_USER;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): NotificationEmailId
    {
        return $this->id;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function verified(): bool
    {
        return $this->verified;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function validateEmail(string $email): string
    {
        $trimmed = trim($email);

        if ($trimmed === '' || filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email address: {$email}");
        }

        return $trimmed;
    }

    private static function validateSource(string $source): string
    {
        if (trim($source) === '') {
            throw new InvalidArgumentException('NotificationEmail source must not be empty.');
        }

        return $source;
    }
}
