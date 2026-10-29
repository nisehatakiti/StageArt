<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Shared\TransactionManagerInterface;
use Throwable;

/**
 * InMemoryTransactionManager plus rollback: every registered repository
 * (anything with snapshot() / restore()) is restored to its state at the
 * start of run() when the operation throws, as START TRANSACTION /
 * ROLLBACK does for WordPressTransactionManager. Nested run() calls join
 * the outermost transaction.
 */
final class RollbackingTransactionManager implements TransactionManagerInterface
{
    /** @var object[] */
    private array $participants;
    private int $depth = 0;
    private int $commits = 0;
    private int $rollbacks = 0;

    public function __construct(object ...$participants)
    {
        $this->participants = $participants;
    }

    public function run(callable $operation)
    {
        if ($this->depth > 0) {
            return $operation();
        }

        $snapshots = array_map(static fn (object $participant) => $participant->snapshot(), $this->participants);
        $this->depth++;

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            foreach ($this->participants as $index => $participant) {
                $participant->restore($snapshots[$index]);
            }

            $this->rollbacks++;

            throw $exception;
        } finally {
            $this->depth--;
        }

        $this->commits++;

        return $result;
    }

    public function isInTransaction(): bool
    {
        return $this->depth > 0;
    }

    public function commits(): int
    {
        return $this->commits;
    }

    public function rollbacks(): int
    {
        return $this->rollbacks;
    }
}
