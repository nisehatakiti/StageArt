<?php

declare(strict_types=1);

namespace StageArt\Domain\Rehearsal;

use InvalidArgumentException;

/**
 * StageArt 予定・活動一般化調査: Rehearsal is reused as the common
 * "schedule/activity" record rather than being copied into a parallel
 * Event/Activity Domain (see the session's own investigation report).
 * This VO is deliberately scoped to activity species that carry their
 * own attendance-tracked schedule (稽古/練習/自主練/ワークショップ/会議/
 * 打ち合わせ/イベント/その他) - it must never include show-run/performance
 * progression concepts (公演/小屋入り/仕込み/場当たり/ゲネプロ/本番), which
 * remain Timetable's responsibility. Mirrors RehearsalStatus's own
 * "validate membership only" shape.
 */
final class RehearsalActivityType
{
    public const REHEARSAL = 'REHEARSAL';
    public const PRACTICE = 'PRACTICE';
    public const SELF_PRACTICE = 'SELF_PRACTICE';
    public const WORKSHOP = 'WORKSHOP';
    public const MEETING = 'MEETING';
    public const DISCUSSION = 'DISCUSSION';
    public const EVENT = 'EVENT';
    public const OTHER = 'OTHER';

    private const VALID = [
        self::REHEARSAL,
        self::PRACTICE,
        self::SELF_PRACTICE,
        self::WORKSHOP,
        self::MEETING,
        self::DISCUSSION,
        self::EVENT,
        self::OTHER,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid RehearsalActivityType: {$value}");
        }

        $this->value = $value;
    }

    /**
     * Existing Rehearsal rows (created before this field existed) are
     * backfilled to REHEARSAL at the DB layer (see RehearsalInstaller's
     * `DEFAULT 'REHEARSAL'`) - this is also the Domain-layer default for
     * any caller that does not specify a species.
     */
    public static function rehearsal(): self
    {
        return new self(self::REHEARSAL);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
