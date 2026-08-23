<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Authentication\EmailVerificationToken;
use StageArt\Domain\Authentication\EmailVerificationTokenRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;

final class InMemoryEmailVerificationTokenRepository implements EmailVerificationTokenRepositoryInterface
{
    /** @var array<string, EmailVerificationToken> */
    private array $tokens = [];

    public function save(EmailVerificationToken $token): void
    {
        $this->tokens[$token->id()->toString()] = $token;
    }

    public function findByTokenHash(string $tokenHash): ?EmailVerificationToken
    {
        foreach ($this->tokens as $token) {
            if ($token->tokenHash() === $tokenHash) {
                return $token;
            }
        }

        return null;
    }

    public function findByUserAccountId(UserAccountId $userAccountId): array
    {
        return array_values(array_filter(
            $this->tokens,
            static fn (EmailVerificationToken $token): bool => $token->userAccountId()->equals($userAccountId)
        ));
    }
}
