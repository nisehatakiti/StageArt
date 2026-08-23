<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use RuntimeException;

/**
 * Thrown when a proposed PrimaryManager does not meet eligibility
 * conditions: an ACTIVE Membership in the Production's Organization
 * (Phase 1 instruction §6: "Organizationに存在しないPersonをProduction
 * のPrimaryManagerとして勝手に登録してはならない"), and an ACTIVE
 * UserAccount (Production.md's "Primary Manager UserAccount
 * Requirement"). Mirrors OwnerTransferTargetNotEligibleException's role
 * for Organization Owner Transfer.
 */
final class PrimaryManagerNotEligibleException extends RuntimeException
{
}
