<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use DateInterval;
use DateTimeImmutable;
use StageArt\Application\Authentication\AuthMailerInterface;
use StageArt\Domain\Authentication\EmailVerificationToken;
use StageArt\Domain\Authentication\EmailVerificationTokenRepositoryInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Idempotent no-op if the caller's EmailCredential is already verified -
 * no token is issued and no email is sent, matching LogoutUseCase's
 * "the client's goal is already satisfied" idempotency reasoning.
 */
final class RequestEmailVerificationUseCase
{
    private const TOKEN_LIFETIME = 'PT24H';

    private PersonRepositoryInterface $people;
    private UserAccountRepositoryInterface $userAccounts;
    private EmailCredentialRepositoryInterface $emailCredentials;
    private EmailVerificationTokenRepositoryInterface $emailVerificationTokens;
    private AuthMailerInterface $mailer;

    public function __construct(
        PersonRepositoryInterface $people,
        UserAccountRepositoryInterface $userAccounts,
        EmailCredentialRepositoryInterface $emailCredentials,
        EmailVerificationTokenRepositoryInterface $emailVerificationTokens,
        AuthMailerInterface $mailer
    ) {
        $this->people = $people;
        $this->userAccounts = $userAccounts;
        $this->emailCredentials = $emailCredentials;
        $this->emailVerificationTokens = $emailVerificationTokens;
        $this->mailer = $mailer;
    }

    public function execute(RequestEmailVerificationCommand $command): void
    {
        $person = $this->people->findByWordPressUserId($command->requestedByWordPressUserId);

        if (! $person) {
            throw new UserAccountNotFoundException('No UserAccount exists for this WordPress user.');
        }

        $userAccount = $this->userAccounts->findByPersonId($person->id());

        if (! $userAccount) {
            throw new UserAccountNotFoundException('No UserAccount exists for this WordPress user.');
        }

        $credential = $this->emailCredentials->findByUserAccountId($userAccount->id());

        if (! $credential) {
            throw new EmailCredentialNotFoundException('No EmailCredential exists for this UserAccount.');
        }

        if ($credential->isEmailVerified()) {
            return;
        }

        $tokenValue = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenValue);

        $token = EmailVerificationToken::create(
            $userAccount->id(),
            $tokenHash,
            (new DateTimeImmutable())->add(new DateInterval(self::TOKEN_LIFETIME))
        );
        $this->emailVerificationTokens->save($token);

        $this->mailer->sendEmailVerificationEmail($credential->email(), $tokenValue);
    }
}
