<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use RuntimeException;

/**
 * Thrown when the Person creating an Organization (and therefore becoming
 * its first OWNER) does not have an ACTIVE UserAccount, per
 * UserAccount.md / Organization.md's Owner UserAccount Requirement. This
 * Use Case never creates a UserAccount on the caller's behalf - the
 * caller must create one first (see CreateUserAccountUseCase), exactly
 * as OwnerTransferUseCase requires an ACTIVE UserAccount for an incoming
 * Owner rather than provisioning one itself.
 */
final class OwnerUserAccountRequiredException extends RuntimeException
{
}
