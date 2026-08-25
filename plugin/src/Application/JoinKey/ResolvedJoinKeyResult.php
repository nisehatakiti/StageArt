<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

/** docs/03-InitialOnboardingAndJoinKey.md §09.2: "これは団体への参加コード
 * です / 劇団○○ / この団体に参加しますか？" - the confirmation screen this
 * DTO drives never shows internal fields, only enough identity for the
 * user to recognize the target before confirming. */
final class ResolvedJoinKeyResult
{
    public string $joinKeyId;
    public string $targetType;
    public string $targetId;
    public string $targetName;
    public ?string $targetSlug;

    public function __construct(string $joinKeyId, string $targetType, string $targetId, string $targetName, ?string $targetSlug)
    {
        $this->joinKeyId = $joinKeyId;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->targetName = $targetName;
        $this->targetSlug = $targetSlug;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'join_key_id' => $this->joinKeyId,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'target_name' => $this->targetName,
            'target_slug' => $this->targetSlug,
        ];
    }
}
