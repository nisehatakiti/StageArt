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
 * time* (Google's own `email_verified` claim) - this Entity is never
 * mutated after creation in this phase (no in-app email-change feature
 * is built yet), so `verified` is fixed for the row's lifetime.
 * `PersonEmailResolver` still checks it explicitly rather than assuming
 * every row is verified, since a future writer (e.g. a Settings-UI
 * email-change flow) could legitimately create an unverified row
 * pending its own confirmation step.
 */
final class NotificationEmail
{
    public const SOURCE_GOOGLE = 'GOOGLE';

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
