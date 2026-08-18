<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use RuntimeException;

/**
 * Thrown when the proposed new Owner does not meet Owner Transfer's
 * eligibility conditions: an existing ACTIVE Membership in the target
 * Organization, and an ACTIVE UserAccount linked to their Person. Owner
 * Transfer never creates a Membership on the target's behalf - adding
 * someone to an Organization and transferring ownership to them are
 * kept as two separate operations.
 */
final class OwnerTransferTargetNotEligibleException extends RuntimeException
{
}
