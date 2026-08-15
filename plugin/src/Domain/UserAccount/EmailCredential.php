<?php

declare(strict_types=1);

namespace StageArt\Domain\UserAccount;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Holds only the already-hashed password (per UserAccount.md: "平文
 * Passwordを保存してはならない"). Hashing itself, Password Login, and
 * Password Reset are not implemented in this slice - this Entity only
 * models the data shape and its own field-level invariants (non-empty
 * email/hash), matching UserAccount.md's "Password Hashアルゴリズムの
 * 具体的選択は、Authentication Infrastructure実装時に決定する".
 */
final class EmailCredential
{
    private EmailCredentialId $id;
    private UserAccountId $userAccountId;
    private string $email;
    private string $passwordHash;
    private ?DateTimeImmutable $emailVerifiedAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        EmailCredentialId $id,
        UserAccountId $userAccountId,
        string $email,
        string $passwordHash,
        ?DateTimeImmutable $emailVerifiedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->userAccountId = $userAccountId;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(UserAccountId $userAccountId, string $email, string $passwordHash): self
    {
        $now = new DateTimeImmutable();

        return new self(
            EmailCredentialId::generate(),
            $userAccountId,
            self::validateEmail($email),
            self::validatePasswordHash($passwordHash),
            null,
            $now,
            $now
        );
    }

    public static function reconstitute(
        EmailCredentialId $id,
        UserAccountId $userAccountId,
        string $email,
        string $passwordHash,
        ?DateTimeImmutable $emailVerifiedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $userAccountId, $email, $passwordHash, $emailVerifiedAt, $createdAt, $updatedAt);
    }

    public function markEmailVerified(): void
    {
        $this->emailVerifiedAt = new DateTimeImmutable();
        $this->touch();
    }

    public function changePasswordHash(string $newPasswordHash): void
    {
        $this->passwordHash = self::validatePasswordHash($newPasswordHash);
        $this->touch();
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    private static function validateEmail(string $email): string
    {
        $trimmed = trim($email);

        if ($trimmed === '' || filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email address: {$email}");
        }

        return $trimmed;
    }

    private static function validatePasswordHash(string $passwordHash): string
    {
        if (trim($passwordHash) === '') {
            throw new InvalidArgumentException('Password hash must not be empty.');
        }

        return $passwordHash;
    }

    public function id(): EmailCredentialId
    {
        return $this->id;
    }

    public function userAccountId(): UserAccountId
    {
        return $this->userAccountId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
