<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Authentication\PasswordResetToken;
use StageArt\Domain\Authentication\PasswordResetTokenRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;

final class InMemoryPasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    /** @var array<string, PasswordResetToken> */
    private array $tokens = [];

    public function save(PasswordResetToken $token): void
    {
        $this->tokens[$token->id()->toString()] = $token;
    }

    public function findByTokenHash(string $tokenHash): ?PasswordResetToken
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
            static fn (PasswordResetToken $token): bool => $token->userAccountId()->equals($userAccountId)
        ));
    }
}
