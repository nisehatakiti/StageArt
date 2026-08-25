<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use RuntimeException;

/** Thrown when a Person already has an ACTIVE or REQUESTED Membership
 * for the target Organization - re-requesting is only meaningful after a
 * REJECTED outcome (see RequestOrganizationMembershipUseCase). */
final class MembershipAlreadyExistsException extends RuntimeException
{
}
