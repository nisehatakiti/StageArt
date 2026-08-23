<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use StageArt\Domain\UserAccount\EmailCredential;

/**
 * Deliberately excludes passwordHash: this Result crosses the
 * Application boundary into Presentation/API responses, and the hash
 * must never be part of any API output.
 */
final class EmailCredentialResult
{
    public string $id;
    public string $userAccountId;
    public string $email;
    public bool $emailVerified;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $userAccountId,
        string $email,
        bool $emailVerified,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->userAccountId = $userAccountId;
        $this->email = $email;
        $this->emailVerified = $emailVerified;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(EmailCredential $credential): self
    {
        return new self(
            $credential->id()->toString(),
            $credential->userAccountId()->toString(),
            $credential->email(),
            $credential->isEmailVerified(),
            $credential->createdAt()->format(DATE_ATOM),
            $credential->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_account_id' => $this->userAccountId,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
