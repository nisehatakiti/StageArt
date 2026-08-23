<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use RuntimeException;

/**
 * Thrown for any Email+Password login failure - unknown email or wrong
 * password alike produce this single exception with the same message, so
 * neither the REST layer nor the client can distinguish "no such account"
 * from "wrong password" (avoids using login as an account-existence
 * oracle, same reasoning as InvalidRefreshTokenException).
 */
final class InvalidCredentialsException extends RuntimeException
{
}
