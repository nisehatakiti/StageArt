<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use RuntimeException;

/**
 * Thrown for any Google ID Token that fails signature, issuer, audience,
 * or expiry verification. Deliberately does not distinguish which check
 * failed in its public message - callers must not leak verification
 * internals to the client, only that authentication failed.
 */
final class InvalidGoogleIdTokenException extends RuntimeException
{
}
