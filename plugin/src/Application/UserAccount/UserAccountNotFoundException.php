<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use RuntimeException;

/**
 * Thrown when an operation requires an existing UserAccount (e.g.
 * registering an EmailCredential) but the requesting WordPress user's
 * Person has none yet. The caller must create a UserAccount first via
 * CreateUserAccountUseCase - this Use Case never creates one implicitly.
 */
final class UserAccountNotFoundException extends RuntimeException
{
}
