<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use RuntimeException;

/**
 * Thrown when a ProductionDelegate already exists for the same
 * (Production, Person, Role) combination. Blueprint allows the same
 * Person to hold multiple *different* Roles on the same Production, but
 * says nothing about permitting the exact same Role twice - rejecting
 * the literal duplicate (also enforced by a DB UNIQUE KEY) directs the
 * caller to Update instead.
 */
final class ProductionDelegateAlreadyExistsException extends RuntimeException
{
}
