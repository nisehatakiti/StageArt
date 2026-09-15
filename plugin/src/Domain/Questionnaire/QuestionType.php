<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use InvalidArgumentException;

/**
 * アンケート実装指示書 §7: V1で実装する6種類のうち、後半の「必須/任意設定」は
 * Question::required (bool) という別の属性であり、QuestionTypeの値そのもの
 * ではない - 残る5種類がこのVOの値になる。
 *
 * A Question's `type` is set once at creation and never changes afterward
 * (Question::class has no `changeType()` method) - §8's "既存回答の意味を
 * 壊す変更を禁止する" is satisfied for `type` trivially by making it
 * immutable, rather than needing runtime guards against a type change.
 */
final class QuestionType
{
    public const SINGLE_CHOICE = 'SINGLE_CHOICE';
    public const MULTIPLE_CHOICE = 'MULTIPLE_CHOICE';
    public const RATING_5 = 'RATING_5';
    public const FREE_TEXT = 'FREE_TEXT';
    public const YES_NO = 'YES_NO';

    private const VALID = [
        self::SINGLE_CHOICE,
        self::MULTIPLE_CHOICE,
        self::RATING_5,
        self::FREE_TEXT,
        self::YES_NO,
    ];

    private const CHOICE_BASED = [
        self::SINGLE_CHOICE,
        self::MULTIPLE_CHOICE,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid QuestionType: {$value}");
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function isChoiceBased(): bool
    {
        return in_array($this->value, self::CHOICE_BASED, true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
