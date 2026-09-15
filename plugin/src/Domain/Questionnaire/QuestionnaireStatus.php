<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use InvalidArgumentException;

/**
 * アンケート実装指示書 §4: DRAFT (編集のみ、公開回答不可) -> PUBLISHED (公開回答
 * 可能、管理者は引き続き質問構成を編集できる) -> CLOSED (公開回答不可、過去回答は
 * 閲覧可能). Which transitions are reachable from which current Status is
 * enforced by Questionnaire::class, not here - matching PerformanceStatus's
 * own precedent of a plain membership-only VO.
 */
final class QuestionnaireStatus
{
    public const DRAFT = 'DRAFT';
    public const PUBLISHED = 'PUBLISHED';
    public const CLOSED = 'CLOSED';

    private const VALID = [
        self::DRAFT,
        self::PUBLISHED,
        self::CLOSED,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid QuestionnaireStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
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
