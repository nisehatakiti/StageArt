<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Shared\TransactionManagerInterface;

/**
 * A plain passthrough: it proves Use Cases route their work through
 * TransactionManagerInterface (rather than touching a database directly)
 * and that exceptions from the wrapped operation propagate unchanged.
 * It does NOT provide real rollback semantics - the in-memory fake
 * repositories have no undo capability, so true atomicity (that a
 * failed save leaves no partial data behind) is only meaningfully
 * verified against the real WordPressTransactionManager on a live
 * database, not here.
 */
final class InMemoryTransactionManager implements TransactionManagerInterface
{
    public function run(callable $operation)
    {
        return $operation();
    }
}
