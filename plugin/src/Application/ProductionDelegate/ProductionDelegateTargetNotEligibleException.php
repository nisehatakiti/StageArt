<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use RuntimeException;

/**
 * Thrown when the proposed delegate does not correspond to an existing
 * Person. Unlike PrimaryManager, ProductionDelegate.md's "Organization
 * Membership Requirement" section explicitly leaves ACTIVE Membership/
 * UserAccount requirements as an open Business Rule ("Organizationおよび
 * AuthorizationのBusiness Ruleに従う...Domain自身は、Membershipの有無を
 * Role Definitionの条件として扱わない"), so this Use Case does not
 * enforce either - only that the target Person exists at all. See the
 * Phase 1 report for this as a disclosed scope decision.
 */
final class ProductionDelegateTargetNotEligibleException extends RuntimeException
{
}
