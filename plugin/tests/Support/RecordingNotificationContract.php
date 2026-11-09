<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use RuntimeException;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Domain\Person\PersonId;

/**
 * Records every notify() call. When given a transaction manager, it also
 * records whether a transaction was still open at that moment, so a test
 * can prove a notification was sent only after the commit. failAll() /
 * failFor() make notify() throw instead, as a failing store would.
 */
final class RecordingNotificationContract implements NotificationContract
{
    /** @var array<int, array{person_id: string, type: string, payload: array<string, mixed>}> */
    private array $sent = [];
    private ?RollbackingTransactionManager $transactions;
    private int $sentInsideTransaction = 0;
    private bool $failAll = false;
    /** @var array<string, true> person ids whose notification fails */
    private array $failFor = [];

    public function __construct(?RollbackingTransactionManager $transactions = null)
    {
        $this->transactions = $transactions;
    }

    public function failAll(): void
    {
        $this->failAll = true;
    }

    public function failFor(PersonId ...$personIds): void
    {
        foreach ($personIds as $personId) {
            $this->failFor[$personId->toString()] = true;
        }
    }

    public function notify(PersonId $personId, string $type, array $payload): void
    {
        if ($this->failAll || isset($this->failFor[$personId->toString()])) {
            throw new RuntimeException('Simulated notification failure.');
        }

        if ($this->transactions !== null && $this->transactions->isInTransaction()) {
            $this->sentInsideTransaction++;
        }

        $this->sent[] = ['person_id' => $personId->toString(), 'type' => $type, 'payload' => $payload];
    }

    /**
     * @return array<int, array{person_id: string, type: string, payload: array<string, mixed>}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    public function sentInsideTransaction(): int
    {
        return $this->sentInsideTransaction;
    }
}
