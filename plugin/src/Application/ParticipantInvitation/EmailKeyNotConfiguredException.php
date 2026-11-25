<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use RuntimeException;

/**
 * R14-B §6.7 (stage 4): the email_key keyring cannot count the sends
 * correctly - no current version, or a version used within the 30-day
 * window is missing - so the invitation is not created / resent (safe
 * side: never count too few). 500 `stageart_email_key_not_configured`.
 * The message names no key value.
 */
final class EmailKeyNotConfiguredException extends RuntimeException
{
}
