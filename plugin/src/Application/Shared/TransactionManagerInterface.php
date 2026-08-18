<?php

declare(strict_types=1);

namespace StageArt\Application\Shared;

/**
 * Lets Application-layer Use Cases run a group of Repository calls as one
 * atomic unit without ever touching wpdb (or any other storage API)
 * directly - only Infrastructure knows how "atomic" is actually achieved.
 *
 * Transaction Boundary is an Application Use Case responsibility per
 * ApplicationArchitecture.md / BackendArchitecture.md (Layer
 * Responsibilities: "Transaction Boundary" is listed under Application,
 * not Domain), and Domain must not depend on persistence-technical
 * concepts (BackendArchitecture.md "Domain Independence"). This Port
 * therefore lives in Application, not Domain\Shared.
 */
interface TransactionManagerInterface
{
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function run(callable $operation);
}
