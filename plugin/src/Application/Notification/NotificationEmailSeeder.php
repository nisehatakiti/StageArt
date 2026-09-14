<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Google認証ユーザーのEmail通知先対応 phase, refined by the
 * "StageArt通知用Email優先順位仕様書": the single place "Google gave us
 * a verified email, should we store it as this Person's notification
 * destination?" is decided, shared by both `AuthenticateWithGoogleUseCase`
 * (primary sign-in/sign-up) and `LinkGoogleIdentityUseCase` (linking
 * Google to an already-authenticated legacy account) - both are
 * "Google認証に成功した際" per this spec, so both seed identically
 * rather than duplicating this rule twice.
 *
 * Deliberately a pure "seed if absent" operation, never an overwrite:
 * once a Person has a NotificationEmail row - however it got there -
 * a later Google login (even with a different Google email) must never
 * change it, so a StageArt-side email a Person has since set through
 * some other means always survives a future Google re-login.
 *
 * §5.2/§9 ケースB: absence of a NotificationEmail row is NOT by itself
 * enough to seed one. A Person who already has an `EmailCredential`
 * (a password-login email already resolvable via
 * `PersonEmailResolver`'s rank-2 fallback) must keep that email as
 * their notification destination when Google is authenticated or
 * linked - Google's email must never silently promote itself ahead of
 * an already-working StageArt email just because no NotificationEmail
 * row happens to exist yet. Only when BOTH NotificationEmail and
 * EmailCredential are absent (a genuinely Google-only Person, §6/§9
 * ケースC) does Google's verified email become the initial value.
 * WordPress user email (rank 3) is deliberately NOT checked here - it
 * is never real for a Google-provisioned account (see
 * WordPressUserProvisioner's own docblock), so it can never be the
 * reason to skip seeding.
 *
 * Never queries the Google API - it only ever reads the claims already
 * verified once by the caller's own GoogleIdTokenVerifierInterface call
 * (this phase's explicit security constraint: no Google access token is
 * ever stored, reused, or passed through this class).
 */
final class NotificationEmailSeeder
{
    private NotificationEmailRepositoryInterface $notificationEmails;
    private UserAccountRepositoryInterface $userAccounts;
    private EmailCredentialRepositoryInterface $emailCredentials;

    public function __construct(
        NotificationEmailRepositoryInterface $notificationEmails,
        UserAccountRepositoryInterface $userAccounts,
        EmailCredentialRepositoryInterface $emailCredentials
    ) {
        $this->notificationEmails = $notificationEmails;
        $this->userAccounts = $userAccounts;
        $this->emailCredentials = $emailCredentials;
    }

    public function seedFromGoogle(PersonId $personId, ?string $googleEmail, bool $googleEmailVerified): void
    {
        if ($googleEmail === null || ! $googleEmailVerified) {
            return;
        }

        if ($this->notificationEmails->findByPersonId($personId) !== null) {
            return;
        }

        if ($this->hasEmailCredential($personId)) {
            return;
        }

        $this->notificationEmails->save(
            NotificationEmail::create($personId, $googleEmail, true, NotificationEmail::SOURCE_GOOGLE)
        );
    }

    private function hasEmailCredential(PersonId $personId): bool
    {
        $userAccount = $this->userAccounts->findByPersonId($personId);

        if ($userAccount === null) {
            return false;
        }

        return $this->emailCredentials->findByUserAccountId($userAccount->id()) !== null;
    }
}
