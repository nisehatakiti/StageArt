<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use RuntimeException;

/**
 * Thrown when a UserAccount that already has an EmailCredential attempts
 * to register another one. UserAccount.md defines EmailCredential as
 * 0..1 per UserAccount (also enforced at the DB layer via a UNIQUE KEY
 * on user_account_id) - this Use Case does not implement credential
 * replacement/rotation in this slice.
 */
final class EmailCredentialAlreadyExistsException extends RuntimeException
{
}
