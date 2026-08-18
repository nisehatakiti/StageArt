<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use RuntimeException;

/**
 * Thrown when an Organization is found to have zero or more than one
 * ACTIVE OWNER Membership at the start of an Owner Transfer. This is a
 * data-integrity failure, not a normal validation error: it is never
 * auto-corrected (see OwnerTransferUseCase) - Owner Recovery for a
 * genuinely Owner-less Organization is explicitly out of scope for this
 * slice and left for future design.
 */
final class OrganizationOwnerInvariantViolatedException extends RuntimeException
{
}
