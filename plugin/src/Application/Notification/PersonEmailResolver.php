<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\UserAccount\WordPressUserLookupInterface;
use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * The official, single place notification-email resolution happens
 * (Google認証ユーザーのEmail通知先対応 phase). Priority order:
 *
 * 1. A verified `NotificationEmail` (Person-keyed; today only ever
 *    seeded from a verified Google email by `NotificationEmailSeeder` -
 *    see that class's own docblock for why it is never overwritten by
 *    a later Google login). An unverified row is deliberately treated
 *    as if it were absent and falls through to the sources below - this
 *    phase never seeds one, but a future writer could leave one
 *    pending its own verification step, and an unverified address must
 *    never be used as a notification destination.
 * 2. `EmailCredential` (the exact resolution order
 *    `ListAllUserAccountsUseCase` already established for the Admin
 *    Console - see that class's own comment): a real email whenever a
 *    password-login credential exists.
 * 3. The WordPress User's own `user_email`, trustworthy only as a last
 *    resort because `WordPressUserProvisioner` gives every Google-
 *    authenticated Person's hidden WordPress User a synthetic
 *    `@users.stageart.invalid` placeholder specifically so it never
 *    collides with (or is mistaken for) a real address - filtered out
 *    explicitly rather than trusted blindly.
 *
 * Returns null (not an exception) when no deliverable address exists in
 * any of the three sources - a Google-only account Google gave no
 * verified email to genuinely has no known email anywhere in StageArt
 * (`ExternalIdentity` deliberately does not store the provider's email -
 * see that class's own docblock); the caller (an Email delivery
 * Adapter) treats null as "skip this Person for Email", never as a
 * dispatch failure.
 */
final class PersonEmailResolver
{
    private const WORDPRESS_PLACEHOLDER_DOMAIN = '@users.stageart.invalid';

    private PersonRepositoryInterface $people;
    private UserAccountRepositoryInterface $userAccounts;
    private EmailCredentialRepositoryInterface $emailCredentials;
    private WordPressUserLookupInterface $wordPressUsers;
    private NotificationEmailRepositoryInterface $notificationEmails;

    public function __construct(
        PersonRepositoryInterface $people,
        UserAccountRepositoryInterface $userAccounts,
        EmailCredentialRepositoryInterface $emailCredentials,
        WordPressUserLookupInterface $wordPressUsers,
        NotificationEmailRepositoryInterface $notificationEmails
    ) {
        $this->people = $people;
        $this->userAccounts = $userAccounts;
        $this->emailCredentials = $emailCredentials;
        $this->wordPressUsers = $wordPressUsers;
        $this->notificationEmails = $notificationEmails;
    }

    public function resolve(PersonId $personId): ?string
    {
        return $this->resolveWithSource($personId)->email;
    }

    /**
     * 通知用Email確認・変更機能 §3: same priority chain as resolve()
     * above (this IS its implementation - resolve() just discards the
     * source), but also discloses WHICH of the three sources the email
     * came from, so Settings display can show a Case-B fallback email
     * without implying it is a saved `NotificationEmail` (仕様書 §3B's
     * explicit "この値を「NotificationEmailとして保存済み」と誤認させな
     * いでください").
     */
    public function resolveWithSource(PersonId $personId): PersonEmailResolution
    {
        $person = $this->people->findById($personId);

        if ($person === null) {
            return new PersonEmailResolution(null, PersonEmailResolution::SOURCE_NONE);
        }

        $notificationEmail = $this->notificationEmails->findByPersonId($personId);

        if ($notificationEmail !== null && $notificationEmail->verified()) {
            return new PersonEmailResolution($notificationEmail->email(), PersonEmailResolution::SOURCE_NOTIFICATION_EMAIL);
        }

        $userAccount = $this->userAccounts->findByPersonId($personId);

        if ($userAccount !== null) {
            $credential = $this->emailCredentials->findByUserAccountId($userAccount->id());

            if ($credential !== null) {
                return new PersonEmailResolution($credential->email(), PersonEmailResolution::SOURCE_EMAIL_CREDENTIAL);
            }
        }

        $wpUser = $this->wordPressUsers->find($person->wordPressUserId());

        if ($wpUser !== null && ! str_ends_with($wpUser->email, self::WORDPRESS_PLACEHOLDER_DOMAIN)) {
            return new PersonEmailResolution($wpUser->email, PersonEmailResolution::SOURCE_WORDPRESS_USER);
        }

        return new PersonEmailResolution(null, PersonEmailResolution::SOURCE_NONE);
    }
}
