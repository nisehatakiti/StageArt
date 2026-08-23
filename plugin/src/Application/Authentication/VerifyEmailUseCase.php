<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Authentication\EmailVerificationTokenRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;

/**
 * Public (token-based, no login required) by design: the user may click
 * the verification link/enter the code on a different device or session
 * than the one that requested it, matching ResetPasswordUseCase's own
 * public consumption pattern - see UserAccountRestController's docblock
 * for the general "request vs. consume" placement rule this follows.
 */
final class VerifyEmailUseCase
{
    private EmailVerificationTokenRepositoryInterface $emailVerificationTokens;
    private EmailCredentialRepositoryInterface $emailCredentials;
    private TransactionManagerInterface $transactions;

    public function __construct(
        EmailVerificationTokenRepositoryInterface $emailVerificationTokens,
        EmailCredentialRepositoryInterface $emailCredentials,
        TransactionManagerInterface $transactions
    ) {
        $this->emailVerificationTokens = $emailVerificationTokens;
        $this->emailCredentials = $emailCredentials;
        $this->transactions = $transactions;
    }

    public function execute(VerifyEmailCommand $command): void
    {
        $hash = hash('sha256', $command->token);
        $verificationToken = $this->emailVerificationTokens->findByTokenHash($hash);

        if (! $verificationToken || ! $verificationToken->isUsable()) {
            throw new InvalidEmailVerificationTokenException('This email verification token is invalid, expired, or already used.');
        }

        $this->transactions->run(function () use ($verificationToken): void {
            $credential = $this->emailCredentials->findByUserAccountId($verificationToken->userAccountId());

            if ($credential) {
                $credential->markEmailVerified();
                $this->emailCredentials->save($credential);
            }

            $verificationToken->consume();
            $this->emailVerificationTokens->save($verificationToken);
        });
    }
}
