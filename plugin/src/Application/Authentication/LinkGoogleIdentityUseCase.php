<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use StageArt\Application\Notification\NotificationEmailSeeder;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\UserAccount\UserAccountResult;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\ExternalIdentity;
use StageArt\Domain\UserAccount\ExternalIdentityRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * The opt-in migration path for an existing WordPress Application
 * Password user (this Phase's design report §9): the caller must
 * already be authenticated through the existing legacy path (resolved
 * from requestedByWordPressUserId, exactly like every other
 * "resolve my own Person" Use Case in this codebase) before they can
 * attach a Google identity to their own UserAccount. This is what makes
 * it safe: there is no email-matching or other automatic linking
 * heuristic (UserAccount.md: "emailを...主キーにしない") - the account
 * being linked to is never ambiguous, it is structurally always the
 * caller's own.
 */
final class LinkGoogleIdentityUseCase
{
    private GoogleIdTokenVerifierInterface $googleVerifier;
    private PersonRepositoryInterface $people;
    private UserAccountRepositoryInterface $userAccounts;
    private ExternalIdentityRepositoryInterface $externalIdentities;
    private TransactionManagerInterface $transactions;
    private NotificationEmailSeeder $notificationEmailSeeder;

    public function __construct(
        GoogleIdTokenVerifierInterface $googleVerifier,
        PersonRepositoryInterface $people,
        UserAccountRepositoryInterface $userAccounts,
        ExternalIdentityRepositoryInterface $externalIdentities,
        TransactionManagerInterface $transactions,
        NotificationEmailSeeder $notificationEmailSeeder
    ) {
        $this->googleVerifier = $googleVerifier;
        $this->people = $people;
        $this->userAccounts = $userAccounts;
        $this->externalIdentities = $externalIdentities;
        $this->transactions = $transactions;
        $this->notificationEmailSeeder = $notificationEmailSeeder;
    }

    public function execute(LinkGoogleIdentityCommand $command): UserAccountResult
    {
        $claims = $this->googleVerifier->verify($command->idToken);

        return $this->transactions->run(function () use ($command, $claims): UserAccountResult {
            $existingIdentity = $this->externalIdentities->findByProviderAndProviderUserId('google', $claims->sub);

            $person = $this->people->findByWordPressUserId($command->requestedByWordPressUserId);

            if (! $person) {
                $person = Person::create($command->requestedByWordPressUserId);
                $this->people->save($person);
            }

            $userAccount = $this->userAccounts->findByPersonId($person->id());

            if (! $userAccount) {
                $userAccount = UserAccount::create($person->id());
                $this->userAccounts->save($userAccount);
            }

            if ($existingIdentity) {
                if (! $existingIdentity->userAccountId()->equals($userAccount->id())) {
                    throw new ExternalIdentityAlreadyLinkedException(
                        'This Google Account is already linked to a different StageArt account.'
                    );
                }

                // Already linked to the caller's own UserAccount - idempotent no-op.
                // Still a successful "Google認証に成功した際" per this
                // phase's instruction, so NotificationEmail is still
                // seeded (if absent) below.
                $this->notificationEmailSeeder->seedFromGoogle($person->id(), $claims->email, $claims->emailVerified);

                return UserAccountResult::fromDomain($userAccount);
            }

            $identity = ExternalIdentity::create($userAccount->id(), 'google', $claims->sub);
            $this->externalIdentities->save($identity);

            // Google認証ユーザーのEmail通知先対応 phase: seeds
            // NotificationEmail only when absent (see
            // NotificationEmailSeeder's own docblock) - applied here too
            // since linking Google to an existing legacy account is
            // still a successful Google authentication.
            $this->notificationEmailSeeder->seedFromGoogle($person->id(), $claims->email, $claims->emailVerified);

            return UserAccountResult::fromDomain($userAccount);
        });
    }
}
