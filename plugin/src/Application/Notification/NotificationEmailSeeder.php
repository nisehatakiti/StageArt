<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;
use StageArt\Domain\Person\PersonId;

/**
 * Google認証ユーザーのEmail通知先対応 phase: the single place "Google
 * gave us a verified email, should we store it as this Person's
 * notification destination?" is decided, shared by both
 * `AuthenticateWithGoogleUseCase` (primary sign-in/sign-up) and
 * `LinkGoogleIdentityUseCase` (linking Google to an already-
 * authenticated legacy account) - both are "Google認証に成功した際" per
 * this phase's instruction, so both seed identically rather than
 * duplicating this rule twice.
 *
 * Deliberately a pure "seed if absent" operation, never an overwrite:
 * once a Person has a NotificationEmail row - however it got there -
 * a later Google login (even with a different Google email) must never
 * change it, so a StageArt-side email a Person has since set through
 * some other means always survives a future Google re-login. Not
 * finding an existing row is the ONLY condition checked; this
 * deliberately does not also require Google's current email to differ
 * from anything already on file.
 *
 * Never queries the Google API - it only ever reads the claims already
 * verified once by the caller's own GoogleIdTokenVerifierInterface call
 * (this phase's explicit security constraint: no Google access token is
 * ever stored, reused, or passed through this class).
 */
final class NotificationEmailSeeder
{
    private NotificationEmailRepositoryInterface $notificationEmails;

    public function __construct(NotificationEmailRepositoryInterface $notificationEmails)
    {
        $this->notificationEmails = $notificationEmails;
    }

    public function seedFromGoogle(PersonId $personId, ?string $googleEmail, bool $googleEmailVerified): void
    {
        if ($googleEmail === null || ! $googleEmailVerified) {
            return;
        }

        if ($this->notificationEmails->findByPersonId($personId) !== null) {
            return;
        }

        $this->notificationEmails->save(
            NotificationEmail::create($personId, $googleEmail, true, NotificationEmail::SOURCE_GOOGLE)
        );
    }
}
