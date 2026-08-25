<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

use StageArt\Domain\JoinKey\JoinKey;

final class JoinKeyResult
{
    public string $id;
    public string $code;
    public string $targetType;
    public string $targetId;
    public string $status;
    public ?string $expiresAt;
    public ?int $maxUses;
    public int $useCount;

    public function __construct(
        string $id,
        string $code,
        string $targetType,
        string $targetId,
        string $status,
        ?string $expiresAt,
        ?int $maxUses,
        int $useCount
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->status = $status;
        $this->expiresAt = $expiresAt;
        $this->maxUses = $maxUses;
        $this->useCount = $useCount;
    }

    public static function fromDomain(JoinKey $joinKey): self
    {
        return new self(
            $joinKey->id()->toString(),
            $joinKey->code(),
            $joinKey->targetType(),
            $joinKey->targetId(),
            $joinKey->status(),
            $joinKey->expiresAt()?->format(DATE_ATOM),
            $joinKey->maxUses(),
            $joinKey->useCount()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'status' => $this->status,
            'expires_at' => $this->expiresAt,
            'max_uses' => $this->maxUses,
            'use_count' => $this->useCount,
        ];
    }
}
