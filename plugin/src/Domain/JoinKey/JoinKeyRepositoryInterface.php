<?php

declare(strict_types=1);

namespace StageArt\Domain\JoinKey;

interface JoinKeyRepositoryInterface
{
    public function save(JoinKey $joinKey): void;

    public function findById(JoinKeyId $id): ?JoinKey;

    public function findByCode(string $normalizedCode): ?JoinKey;

    /** The single "currently active" key for a target, per JoinKey.md's
     * "初期UIでは「現在有効な参加コード」を中心に扱い" - multiple keys per
     * target are supported by the schema/domain, but the initial
     * Organization/Production admin UI only surfaces one at a time.
     *
     * @return JoinKey[]
     */
    public function findByTarget(string $targetType, string $targetId): array;
}
