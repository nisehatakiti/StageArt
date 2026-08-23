<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use RuntimeException;

/**
 * Thrown when a Refresh Token is not found, expired, or already revoked.
 * Deliberately a single exception for all three cases - the client-facing
 * response is the same either way ("log in again"), and distinguishing
 * them would only help an attacker probe token validity.
 */
final class InvalidRefreshTokenException extends RuntimeException
{
}
