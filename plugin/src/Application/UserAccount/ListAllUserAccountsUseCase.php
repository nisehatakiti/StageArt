<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * StageArt Admin Console V1: platform-wide account listing for the
 * Account Management screen (docs/architecture/StageArtAdminConsole.md's
 * "Person / account overview"). Authorization is enforced one layer up,
 * by AccountManagementAdminPage's own current_user_can()
 * ('stageart_manage_accounts') check before this Use Case is ever
 * invoked - this Use Case itself does not (and, per UserAccount.md,
 * should not) know about WordPress Capabilities.
 *
 * N+1 Person/WordPress User/EmailCredential lookups per account are
 * accepted for this V1 - StageArt has no account volume yet that would
 * make this a real cost, and every existing Repository interface here
 * is reused unchanged rather than adding a bespoke JOIN query.
 *
 * DISABLED accounts (Admin Console's "削除") are excluded from the
 * default result set - the same "disappears from the active listing"
 * behavior DeleteOrganizationUseCase's own docblock already establishes
 * for archived Organizations, not a new convention invented here.
 * SUSPENDED ("ブロック") accounts remain visible, since an admin still
 * needs to see and eventually unblock them.
 */
final class ListAllUserAccountsUseCase
{
    private UserAccountRepositoryInterface $userAccounts;
    private PersonRepositoryInterface $people;
    private EmailCredentialRepositoryInterface $emailCredentials;
    private WordPressUserLookupInterface $wordPressUsers;

    public function __construct(
        UserAccountRepositoryInterface $userAccounts,
        PersonRepositoryInterface $people,
        EmailCredentialRepositoryInterface $emailCredentials,
        WordPressUserLookupInterface $wordPressUsers
    ) {
        $this->userAccounts = $userAccounts;
        $this->people = $people;
        $this->emailCredentials = $emailCredentials;
        $this->wordPressUsers = $wordPressUsers;
    }

    /**
     * @return AdminAccountResult[]
     */
    public function execute(): array
    {
        $results = [];

        foreach ($this->userAccounts->findAll() as $userAccount) {
            if ($userAccount->status()->toString() === 'DISABLED') {
                continue;
            }

            $person = $this->people->findById($userAccount->personId());

            if (! $person) {
                // Data integrity edge case only (UserAccount.md requires
                // every UserAccount to reference a Person) - skipped
                // rather than fatally erroring the whole admin screen
                // over one inconsistent row.
                continue;
            }

            $wpUser = $this->wordPressUsers->find($person->wordPressUserId());
            $credential = $this->emailCredentials->findByUserAccountId($userAccount->id());

            $name = trim(($person->familyName() ?? '') . ' ' . ($person->givenName() ?? ''));
            if ($name === '' && $wpUser !== null) {
                $name = $wpUser->displayName;
            }
            if ($name === '') {
                $name = '(no name)';
            }

            // A real (non-.invalid) email always comes from EmailCredential
            // when one exists (the WordPress User's own user_email is a
            // synthetic placeholder for Google-provisioned accounts - see
            // WordPressUserProvisioner's own docblock); WordPress's
            // user_email is only ever the real address for a WordPress
            // User created some other way (unlikely, but a safe fallback).
            $email = $credential !== null ? $credential->email() : ($wpUser->email ?? '');

            $results[] = new AdminAccountResult(
                $userAccount->id()->toString(),
                $person->id()->toString(),
                $name,
                $email,
                $userAccount->status()->toString(),
                $credential !== null
            );
        }

        return $results;
    }
}
