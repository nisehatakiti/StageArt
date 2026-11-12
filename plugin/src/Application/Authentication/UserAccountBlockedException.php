<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use RuntimeException;

/**
 * StageArt Admin Console V1: thrown when a UserAccount whose status is
 * not ACTIVE (SUSPENDED via Admin Console "ブロック", or DISABLED via
 * "削除" - see docs/04-DomainModel/UserAccount.md's Status section)
 * attempts to establish or renew a session. Distinct from
 * InvalidCredentialsException: the credential itself was valid, so
 * telling the user their account was blocked (rather than reusing the
 * generic invalid-credentials message) is not an enumeration risk here.
 */
final class UserAccountBlockedException extends RuntimeException
{
}
