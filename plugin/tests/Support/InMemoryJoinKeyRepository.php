<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyId;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;

final class InMemoryJoinKeyRepository implements JoinKeyRepositoryInterface
{
    /** @var array<string, JoinKey> */
    private array $joinKeys = [];

    public function save(JoinKey $joinKey): void
    {
        $this->joinKeys[$joinKey->id()->toString()] = $joinKey;
    }

    public function findById(JoinKeyId $id): ?JoinKey
    {
        return $this->joinKeys[$id->toString()] ?? null;
    }

    public ?LockLog $lockLog = null;

    /** R14-B stage 3: the next recordUseIfUsable() throws this (a failed write). */
    public ?\Throwable $failNextUse = null;

    /** Mirrors the conditional UPDATE: counts the use only while the key is usable at `$now`. */
    public function recordUseIfUsable(JoinKeyId $id, \DateTimeImmutable $now): bool
    {
        if ($this->lockLog !== null) {
            $this->lockLog->record('join_key');
        }

        if ($this->failNextUse !== null) {
            $failure = $this->failNextUse;
            $this->failNextUse = null;

            throw $failure;
        }

        $joinKey = $this->findById($id);

        if ($joinKey === null
            || $joinKey->status() !== JoinKey::STATUS_ACTIVE
            || ($joinKey->expiresAt() !== null && $joinKey->expiresAt() <= $now)
            || ($joinKey->maxUses() !== null && $joinKey->useCount() >= $joinKey->maxUses())
        ) {
            return false;
        }

        $joinKey->recordUse();

        return true;
    }

    /**
     * @return array<string, JoinKey>
     */
    public function snapshot(): array
    {
        return array_map(static fn (JoinKey $joinKey): JoinKey => clone $joinKey, $this->joinKeys);
    }

    /**
     * @param array<string, JoinKey> $snapshot
     */
    public function restore(array $snapshot): void
    {
        $this->joinKeys = $snapshot;
    }

    public function findByCode(string $normalizedCode): ?JoinKey
    {
        foreach ($this->joinKeys as $joinKey) {
            if ($joinKey->code() === $normalizedCode) {
                return $joinKey;
            }
        }

        return null;
    }

    public function findByTarget(string $targetType, string $targetId): array
    {
        return array_values(array_filter(
            $this->joinKeys,
            static fn (JoinKey $joinKey): bool => $joinKey->targetType() === $targetType && $joinKey->targetId() === $targetId
        ));
    }
}
