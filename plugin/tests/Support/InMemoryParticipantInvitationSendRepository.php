<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSendRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B stage 4: the send records in memory, with their status. Snapshot /
 * restore for RollbackingTransactionManager; failNextRead / failNextAdd
 * inject a failed read or write.
 */
final class InMemoryParticipantInvitationSendRepository implements ParticipantInvitationSendRepositoryInterface
{
    /** @var array<string, array{send: ParticipantInvitationSend, status: string, completedAt: ?DateTimeImmutable}> */
    private array $rows = [];

    public ?\Throwable $failNextRead = null;
    public ?\Throwable $failNextAdd = null;
    public ?LockLog $lockLog = null;

    public function add(ParticipantInvitationSend $send): void
    {
        if ($this->lockLog !== null) {
            $this->lockLog->record('send');
        }

        if ($this->failNextAdd !== null) {
            $failure = $this->failNextAdd;
            $this->failNextAdd = null;

            throw $failure;
        }

        $this->rows[$send->id()] = ['send' => $send, 'status' => ParticipantInvitationSend::PENDING, 'completedAt' => null];
    }

    public function keyVersionsSince(ProductionId $productionId, DateTimeImmutable $since): array
    {
        $this->failIfAsked();
        $versions = [];

        foreach ($this->rows as $row) {
            if ($row['send']->productionId()->equals($productionId) && $row['send']->requestedAt() >= $since) {
                $versions[$row['send']->emailKeyVersion()] = true;
            }
        }

        return array_keys($versions);
    }

    public function requestedAtsForKeysSince(ProductionId $productionId, DateTimeImmutable $since, array $keysByVersion): array
    {
        $this->failIfAsked();
        $times = [];

        foreach ($this->rows as $row) {
            $send = $row['send'];

            if ($send->productionId()->equals($productionId)
                && $send->requestedAt() >= $since
                && ($keysByVersion[$send->emailKeyVersion()] ?? null) === $send->emailKey()
            ) {
                $times[] = $send->requestedAt();
            }
        }

        sort($times);

        return $times;
    }

    public function completeIfPending(string $sendId, string $status, DateTimeImmutable $completedAt): bool
    {
        if (! ParticipantInvitationSend::isResult($status)) {
            throw new InvalidArgumentException("Invalid send result: {$status}");
        }

        if (! isset($this->rows[$sendId]) || $this->rows[$sendId]['status'] !== ParticipantInvitationSend::PENDING) {
            return false;
        }

        $this->rows[$sendId]['status'] = $status;
        $this->rows[$sendId]['completedAt'] = $completedAt;

        return true;
    }

    private function failIfAsked(): void
    {
        if ($this->failNextRead !== null) {
            $failure = $this->failNextRead;
            $this->failNextRead = null;

            throw $failure;
        }
    }

    /** Test seed: a send already accepted at $requestedAt (any status). */
    public function seed(ParticipantInvitationSend $send, string $status = ParticipantInvitationSend::SENT): void
    {
        $this->rows[$send->id()] = ['send' => $send, 'status' => $status, 'completedAt' => null];
    }

    /** @return array<string, string> send id => status, in insertion order */
    public function statuses(): array
    {
        return array_map(static fn (array $row): string => $row['status'], $this->rows);
    }

    /** @return ParticipantInvitationSend[] */
    public function all(): array
    {
        return array_values(array_map(static fn (array $row): ParticipantInvitationSend => $row['send'], $this->rows));
    }

    public function snapshot(): array
    {
        return $this->rows;
    }

    public function restore(array $snapshot): void
    {
        $this->rows = $snapshot;
    }
}
