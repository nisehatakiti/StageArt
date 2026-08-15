<?php

declare(strict_types=1);

namespace StageArt\Domain\Shared;

/**
 * Lets Application-layer Use Cases run a group of Repository calls as one
 * atomic unit without ever touching wpdb (or any other storage API)
 * directly - only Infrastructure knows how "atomic" is actually achieved.
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
