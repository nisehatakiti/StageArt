<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use RuntimeException;

/**
 * Thrown when a Person who already has a UserAccount attempts to create
 * another one. UserAccount is 1:1 with Person (enforced at the DB layer
 * too, via a UNIQUE KEY on person_id), so this Use Case rejects the
 * duplicate rather than silently returning the existing UserAccount -
 * an explicit error is clearer than an implicit no-op for a "create"
 * operation.
 */
final class UserAccountAlreadyExistsException extends RuntimeException
{
}
