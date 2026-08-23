<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use RuntimeException;

/**
 * Thrown when an operation requires the caller's UserAccount to already
 * have an EmailCredential (e.g. changing a password, requesting email
 * verification) but none exists yet - e.g. a Google-only account that
 * never set up Email+Password. The caller must register one first via
 * POST /user-accounts/email-credential.
 */
final class EmailCredentialNotFoundException extends RuntimeException
{
}
