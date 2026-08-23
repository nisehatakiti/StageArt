<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use InvalidArgumentException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Authentication\PasswordResetTokenRepositoryInterface;
use StageArt\Domain\Authentication\RefreshTokenRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;

/**
 * On success, revokes every existing Refresh Token for the affected
 * UserAccount (this Phase's proposed security default, confirmed in the
 * design review: a password reset should end any other still-active
 * session, e.g. a device the real owner no longer controls). The Access
 * Token already issued to such a session is not separately revocable
 * (stateless by design) and simply expires within its own 1-hour
 * lifetime.
 */
final class ResetPasswordUseCase
{
    private const MIN_PASSWORD_LENGTH = 8;

    private PasswordResetTokenRepositoryInterface $passwordResetTokens;
    private EmailCredentialRepositoryInterface $emailCredentials;
    private RefreshTokenRepositoryInterface $refreshTokens;
    private TransactionManagerInterface $transactions;

    public function __construct(
        PasswordResetTokenRepositoryInterface $passwordResetTokens,
        EmailCredentialRepositoryInterface $emailCredentials,
        RefreshTokenRepositoryInterface $refreshTokens,
        TransactionManagerInterface $transactions
    ) {
        $this->passwordResetTokens = $passwordResetTokens;
        $this->emailCredentials = $emailCredentials;
        $this->refreshTokens = $refreshTokens;
        $this->transactions = $transactions;
    }

    public function execute(ResetPasswordCommand $command): void
    {
        if (strlen($command->newPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        $hash = hash('sha256', $command->token);
        $resetToken = $this->passwordResetTokens->findByTokenHash($hash);

        if (! $resetToken || ! $resetToken->isUsable()) {
            throw new InvalidPasswordResetTokenException('This password reset token is invalid, expired, or already used.');
        }

        $this->transactions->run(function () use ($resetToken, $command): void {
            $credential = $this->emailCredentials->findByUserAccountId($resetToken->userAccountId());

            if (! $credential) {
                throw new InvalidPasswordResetTokenException('This password reset token is invalid, expired, or already used.');
            }

            $credential->changePasswordHash(password_hash($command->newPassword, PASSWORD_DEFAULT));
            $this->emailCredentials->save($credential);

            $resetToken->consume();
            $this->passwordResetTokens->save($resetToken);

            foreach ($this->refreshTokens->findByUserAccountId($resetToken->userAccountId()) as $refreshToken) {
                $refreshToken->revoke();
                $this->refreshTokens->save($refreshToken);
            }
        });
    }
}
