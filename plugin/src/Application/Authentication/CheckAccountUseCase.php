<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\ExternalIdentityRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * `POST /auth/account/check` (login screen, step 1): which sign-in path an
 * address leads to, from the existing stores only - nothing is written
 * except the rate-limit counter, and nothing is created or linked.
 *
 * 1. Rate limit (AccountCheckRateLimit) - counted before anything else,
 *    including the format check.
 * 2. The address must look like an e-mail address (422 otherwise).
 * 3. An EmailCredential with this address (the same lookup the e-mail login
 *    uses - AuthenticateWithEmailUseCase): its UserAccount decides.
 *    SUSPENDED / DISABLED -> DISABLED. A linked Google ExternalIdentity
 *    adds `google`. Verified -> PASSWORD_LOGIN / MULTIPLE_AUTH_METHODS;
 *    not verified -> EMAIL_VERIFICATION_REQUIRED.
 * 4. Otherwise a Google-only account: a VERIFIED NotificationEmail with
 *    this address (seeded only from a Google-verified address by
 *    NotificationEmailSeeder - a Gmail address is never assumed to be a
 *    Google account) whose account has a Google ExternalIdentity ->
 *    GOOGLE_LOGIN (DISABLED when every such account is blocked).
 * 5. Otherwise NOT_FOUND.
 *
 * The answer reveals whether the address is registered - hence the rate
 * limit. The e-mail login's own `stageart_invalid_credentials` (which does
 * not tell the two cases apart) is unchanged.
 */
final class CheckAccountUseCase
{
    private const MAX_EMAIL_LENGTH = 254;
    private const GOOGLE = 'google';

    private EmailCredentialRepositoryInterface $emailCredentials;
    private UserAccountRepositoryInterface $userAccounts;
    private ExternalIdentityRepositoryInterface $externalIdentities;
    private NotificationEmailRepositoryInterface $notificationEmails;
    private AccountCheckRateLimit $rateLimit;

    public function __construct(
        EmailCredentialRepositoryInterface $emailCredentials,
        UserAccountRepositoryInterface $userAccounts,
        ExternalIdentityRepositoryInterface $externalIdentities,
        NotificationEmailRepositoryInterface $notificationEmails,
        AccountCheckRateLimit $rateLimit
    ) {
        $this->emailCredentials = $emailCredentials;
        $this->userAccounts = $userAccounts;
        $this->externalIdentities = $externalIdentities;
        $this->notificationEmails = $notificationEmails;
        $this->rateLimit = $rateLimit;
    }

    /**
     * @throws \StageArt\Application\RateLimit\RateLimitedException
     * @throws InvalidAccountCheckEmailException
     */
    public function execute(CheckAccountCommand $command): CheckAccountResult
    {
        $email = trim($command->email);

        $this->rateLimit->assertAllowed($command->clientAddress, $email);

        if ($email === '' || strlen($email) > self::MAX_EMAIL_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidAccountCheckEmailException('A valid e-mail address is required.');
        }

        $credential = $this->emailCredentials->findByEmail($email);

        if ($credential !== null) {
            $account = $this->userAccounts->findById($credential->userAccountId());

            if ($account === null) {
                return new CheckAccountResult(CheckAccountResult::NOT_FOUND, []);
            }

            if (! $account->isActive()) {
                return new CheckAccountResult(CheckAccountResult::DISABLED, []);
            }

            $hasGoogle = $this->hasGoogleIdentity($account);

            if (! $credential->isEmailVerified()) {
                return new CheckAccountResult(
                    CheckAccountResult::EMAIL_VERIFICATION_REQUIRED,
                    $hasGoogle ? [CheckAccountResult::METHOD_GOOGLE] : []
                );
            }

            return $hasGoogle
                ? new CheckAccountResult(CheckAccountResult::MULTIPLE_AUTH_METHODS, [CheckAccountResult::METHOD_PASSWORD, CheckAccountResult::METHOD_GOOGLE])
                : new CheckAccountResult(CheckAccountResult::PASSWORD_LOGIN, [CheckAccountResult::METHOD_PASSWORD]);
        }

        $googleAccountFound = false;
        $activeGoogleAccountFound = false;

        foreach ($this->notificationEmails->findVerifiedByEmail($email) as $notificationEmail) {
            $account = $this->userAccounts->findByPersonId($notificationEmail->personId());

            if ($account === null || ! $this->hasGoogleIdentity($account)) {
                continue;
            }

            $googleAccountFound = true;

            if ($account->isActive()) {
                $activeGoogleAccountFound = true;
            }
        }

        if ($activeGoogleAccountFound) {
            return new CheckAccountResult(CheckAccountResult::GOOGLE_LOGIN, [CheckAccountResult::METHOD_GOOGLE]);
        }

        if ($googleAccountFound) {
            return new CheckAccountResult(CheckAccountResult::DISABLED, []);
        }

        return new CheckAccountResult(CheckAccountResult::NOT_FOUND, []);
    }

    private function hasGoogleIdentity(UserAccount $account): bool
    {
        foreach ($this->externalIdentities->findByUserAccountId($account->id()) as $identity) {
            if ($identity->provider() === self::GOOGLE) {
                return true;
            }
        }

        return false;
    }
}
