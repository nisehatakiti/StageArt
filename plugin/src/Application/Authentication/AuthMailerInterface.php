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
}
