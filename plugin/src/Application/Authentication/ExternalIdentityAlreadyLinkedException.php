<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use RuntimeException;

/**
 * Thrown when a (provider, providerUserId) pair is already linked to a
 * UserAccount other than the caller's own - e.g. a Google account that
 * already belongs to a different StageArt UserAccount attempting to
 * link to a second one.
 */
final class ExternalIdentityAlreadyLinkedException extends RuntimeException
{
}
