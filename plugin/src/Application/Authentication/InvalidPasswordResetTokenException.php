<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use RuntimeException;

/**
 * Thrown when a Password Reset Token is not found, expired, or already
 * consumed - a single exception for all three, matching
 * InvalidRefreshTokenException's reasoning (the client-facing response is
 * the same either way, and distinguishing them would only help an
 * attacker probe token validity).
 */
final class InvalidPasswordResetTokenException extends RuntimeException
{
}
