<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\UserAccount\WordPressUserLookupInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Reuses the exact resolution order `ListAllUserAccountsUseCase` already
 * established for the Admin Console (see that class's own comment): a
 * real email always comes from `EmailCredential` when one exists; a
 * WordPress User's own `user_email` is only trustworthy as a fallback
 * because `WordPressUserProvisioner` gives every Google-authenticated
 * Person's hidden WordPress User a synthetic `@users.stageart.invalid`
 * placeholder specifically so it never collides with (or is mistaken
 * for) a real address - this resolver filters that placeholder out
 * explicitly rather than trusting the fallback blindly.
 *
 * Returns null (not an exception) when no deliverable address exists -
 * Google-only accounts with no EmailCredential genuinely have no known
 * email anywhere in StageArt today (`ExternalIdentity` deliberately does
 * not store the provider's email - see that class's own docblock); the
 * caller (an Email delivery Adapter) treats null as "skip this Person
 * for Email", not as a failure.
 */
final class PersonEmailResolver
{
    private const WORDPRESS_PLACEHOLDER_DOMAIN = '@users.stageart.invalid';

    private PersonRepositoryInterface $people;
    private UserAccountRepositoryInterface $userAccounts;
    private EmailCredentialRepositoryInterface $emailCredentials;
    private WordPressUserLookupInterface $wordPressUsers;

    public function __construct(
        PersonRepositoryInterface $people,
        UserAccountRepositoryInterface $userAccounts,
        EmailCredentialRepositoryInterface $emailCredentials,
        WordPressUserLookupInterface $wordPressUsers
    ) {
        $this->people = $people;
        $this->userAccounts = $userAccounts;
        $this->emailCredentials = $emailCredentials;
        $this->wordPressUsers = $wordPressUsers;
    }

    public function resolve(PersonId $personId): ?string
    {
        $person = $this->people->findById($personId);

        if ($person === null) {
            return null;
        }

        $userAccount = $this->userAccounts->findByPersonId($personId);

        if ($userAccount !== null) {
            $credential = $this->emailCredentials->findByUserAccountId($userAccount->id());

            if ($credential !== null) {
                return $credential->email();
            }
        }

        $wpUser = $this->wordPressUsers->find($person->wordPressUserId());

        if ($wpUser !== null && ! str_ends_with($wpUser->email, self::WORDPRESS_PLACEHOLDER_DOMAIN)) {
            return $wpUser->email;
        }

        return null;
    }
}
