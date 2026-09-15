<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use InvalidArgumentException;

/**
 * §9/§10: one answer to one Question, inside one anonymous
 * QuestionnaireResponse. Deliberately carries nothing beyond
 * `questionId`/`value` - no respondent-identifying field could ever be
 * added here without also adding it to QuestionnaireResponse's own
 * constructor, which §10's own enumerated ban list exists to prevent.
 *
 * `value` shape depends on the answered Question's QuestionType (validated
 * against the real Question by the Application layer's
 * SubmitQuestionnaireResponseUseCase, not here - this VO only enforces the
 * shape is one of the four JSON-safe primitives a value can legally take):
 *  - SINGLE_CHOICE: string (a QuestionChoice id)
 *  - MULTIPLE_CHOICE: string[] (QuestionChoice ids)
 *  - RATING_5: int (1-5)
 *  - FREE_TEXT: string
 *  - YES_NO: string ('YES'|'NO')
 */
final class ResponseAnswer
{
    private string $questionId;
    /** @var string|int|string[] */
    private $value;

    /**
     * @param string|int|string[] $value
     */
    private function __construct(string $questionId, $value)
    {
        $this->questionId = $questionId;
        $this->value = $value;
    }

    /**
     * @param string|int|string[] $value
     */
    public static function create(string $questionId, $value): self
    {
        if (! is_string($value) && ! is_int($value) && ! self::isStringList($value)) {
            throw new InvalidArgumentException('ResponseAnswer value must be a string, an int, or a list of strings.');
        }

        return new self($questionId, $value);
    }

    private static function isStringList($value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }

    public function questionId(): string
    {
        return $this->questionId;
    }

    /**
     * @return string|int|string[]
     */
    public function value()
    {
        return $this->value;
    }
}
