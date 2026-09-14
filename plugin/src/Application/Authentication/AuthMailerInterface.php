<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * Port for delivering the raw opaque token value to the account owner's
 * email address, for both the password-reset and email-verification
 * flows. The Application layer has no knowledge of wp_mail(), message
 * templates, or how a token is eventually presented to the user (e.g. as
 * a deep link vs. a plain code) - that presentation decision belongs to
 * whatever consumes this token (mobile-rn), not to this Backend Phase;
 * see this Phase's implementation report for the disclosed scope limit.
 */
interface AuthMailerInterface
{
    public function sendPasswordResetEmail(string $toEmail, string $token): void;

    public function sendEmailVerificationEmail(string $toEmail, string $token): void;

    /**
     * 通知用Email確認・変更機能 §17: a distinct message from
     * sendEmailVerificationEmail() above - this confirms a candidate
     * *notification* destination, not an EmailCredential login email,
     * and is deliberately not the Notification Email Adapter's own
     * `wp_mail()` call either (this is "通知先Email変更の本人確認", not
     * an "Email Notification" - see that Adapter's own docblock for why
     * the two stay separate responsibilities).
     */
    public function sendNotificationEmailChangeVerificationEmail(string $toEmail, string $token): void;
}
