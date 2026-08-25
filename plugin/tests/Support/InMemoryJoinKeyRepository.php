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
