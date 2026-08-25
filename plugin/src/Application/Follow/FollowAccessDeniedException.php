<?php

declare(strict_types=1);

namespace StageArt\Application\Follow;

use RuntimeException;

/** Defensive only: every authenticated WordPress user has a Person row
 * from registration onward (see RegisterWithEmailUseCase/
 * AuthenticateWithGoogleUseCase), so this should never actually be
 * reachable in practice - kept for the same "no Person" edge case every
 * other Organization-adjacent Use Case in this codebase guards against. */
final class FollowAccessDeniedException extends RuntimeException
{
}
