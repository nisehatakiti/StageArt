<?php

declare(strict_types=1);

namespace StageArt\Application\Admin;

use RuntimeException;

/**
 * Carries wp_insert_user()'s own error message verbatim (e.g. "duplicate
 * username", "invalid email") - never a password or any other secret,
 * since none is ever passed through this exception.
 */
final class AdminConsoleAccountCreationException extends RuntimeException
{
}
