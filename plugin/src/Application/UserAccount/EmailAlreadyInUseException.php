<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use RuntimeException;

/**
 * Thrown when the requested email address is already registered to a
 * different UserAccount's EmailCredential (also enforced at the DB
 * layer via a UNIQUE KEY on email).
 */
final class EmailAlreadyInUseException extends RuntimeException
{
}
