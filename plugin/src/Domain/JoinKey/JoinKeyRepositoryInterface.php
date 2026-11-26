<?php

declare(strict_types=1);

namespace StageArt\Domain\JoinKey;

interface JoinKeyRepositoryInterface
{
    public function save(JoinKey $joinKey): void;

    public function findById(JoinKeyId $id): ?JoinKey;

    public function findByCode(string $normalizedCode): ?JoinKey;

    /**
     * R14-B §5.4 / §7.1 (U13 C案): counts one use of the key atomically -
     * a single conditional UPDATE that adds 1 to use_count only while the
     * key is ACTIVE, below max_uses (or unlimited) and not past expires_at
     * (or without one), and that, like JoinKey::recordUse(), turns the key
     * EXHAUSTED when this use reaches max_uses. Run inside the join
     * request's transaction after the Participant row is saved (lock order
     * Production -> Participant -> Join Key).
     *
     * true = the use was counted; false = the key could not be used (the
     * caller answers 404 and rolls the whole request back). Throws when the
     * write fails.
     */
    public function recordUseIfUsable(JoinKeyId $id, \DateTimeImmutable $now): bool;

    /** The single "currently active" key for a target, per JoinKey.md's
     * "初期UIでは「現在有効な参加コード」を中心に扱い" - multiple keys per
     * target are supported by the schema/domain, but the initial
     * Organization/Production admin UI only surfaces one at a time.
     *
     * @return JoinKey[]
     */
    public function findByTarget(string $targetType, string $targetId): array;
}
