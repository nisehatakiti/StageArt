<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

/**
 * R14-B stage 3: the order in which the in-memory Repositories were asked
 * for row locks within a test, to check the §5.4 lock order
 * (Production -> invitation -> Participant -> Join Key -> delegate).
 */
final class LockLog
{
    /** @var string[] */
    private array $entries = [];

    public function reset(): void
    {
        $this->entries = [];
    }

    public function record(string $what): void
    {
        $this->entries[] = $what;
    }

    /**
     * @return string[]
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * The entries with consecutive repeats collapsed (two delegate rows ->
     * one 'delegate').
     *
     * @return string[]
     */
    public function order(): array
    {
        $order = [];

        foreach ($this->entries as $entry) {
            if ($order === [] || end($order) !== $entry) {
                $order[] = $entry;
            }
        }

        return $order;
    }
}
