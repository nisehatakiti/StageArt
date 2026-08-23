<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use DateInterval;
use DateTimeImmutable;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Authentication\PasswordResetToken;
use StageArt\Domain\Authentication\PasswordResetTokenRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;

/**
 * Always completes successfully regardless of whether the email address
 * is registered - the caller cannot distinguish "reset email sent" from
 * "no such account" (avoids using this endpoint as an account-existence
 * oracle). Any previously-issued, still-usable reset token for the same
 * UserAccount is consumed before issuing the new one, so only the most
 * recently requested link/code is ever valid at a time.
 */
final class RequestPasswordResetUseCase
{
    private const TOKEN_LIFETIME = 'PT1H';

    private EmailCredentialRepositoryInterface $emailCredentials;
    private PasswordResetTokenRepositoryInterface $passwordResetTokens;
    private AuthMailerInterface $mailer;
    private TransactionManagerInterface $transactions;

    public function __construct(
        EmailCredentialRepositoryInterface $emailCredentials,
        PasswordResetTokenRepositoryInterface $passwordResetTokens,
        AuthMailerInterface $mailer,
        TransactionManagerInterface $transactions
    ) {
        $this->emailCredentials = $emailCredentials;
        $this->passwordResetTokens = $passwordResetTokens;
        $this->mailer = $mailer;
        $this->transactions = $transactions;
    }

    public function execute(RequestPasswordResetCommand $command): void
    {
        $credential = $this->emailCredentials->findByEmail($command->email);

        if (! $credential) {
            return;
        }

        $this->transactions->run(function () use ($credential): void {
            foreach ($this->passwordResetTokens->findByUserAccountId($credential->userAccountId()) as $existing) {
                if ($existing->isUsable()) {
                    $existing->consume();
                    $this->passwordResetTokens->save($existing);
                }
            }

            $tokenValue = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $tokenValue);

            $token = PasswordResetToken::create(
                $credential->userAccountId(),
                $tokenHash,
                (new DateTimeImmutable())->add(new DateInterval(self::TOKEN_LIFETIME))
            );
            $this->passwordResetTokens->save($token);

            $this->mailer->sendPasswordResetEmail($credential->email(), $tokenValue);
        });
    }
}
