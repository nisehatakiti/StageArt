<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

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

    public function __construct(
        GoogleIdTokenVerifierInterface $googleVerifier,
        PersonRepositoryInterface $people,
        UserAccountRepositoryInterface $userAccounts,
        ExternalIdentityRepositoryInterface $externalIdentities,
        TransactionManagerInterface $transactions
    ) {
        $this->googleVerifier = $googleVerifier;
        $this->people = $people;
        $this->userAccounts = $userAccounts;
        $this->externalIdentities = $externalIdentities;
        $this->transactions = $transactions;
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
                return UserAccountResult::fromDomain($userAccount);
            }

            $identity = ExternalIdentity::create($userAccount->id(), 'google', $claims->sub);
            $this->externalIdentities->save($identity);

            return UserAccountResult::fromDomain($userAccount);
        });
    }
}
