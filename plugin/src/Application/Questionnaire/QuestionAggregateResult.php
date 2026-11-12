<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

/**
 * §37/§38/§39: one Question's anonymous tally - never anything that could
 * be traced back to who submitted a particular answer (no per-Response
 * breakdown, no "回答者1" style identifiers - just totals per Question).
 */
final class QuestionAggregateResult
{
    public string $questionId;
    public string $text;
    public string $type;
    /** @var array<int, array{choice_id: string, label: string, count: int}>|null */
    public ?array $choiceCounts;
    /** @var array<int, int>|null keyed "1".."5" */
    public ?array $ratingCounts;
    public ?float $ratingAverage;
    public ?int $yesCount;
    public ?int $noCount;
    /** @var string[]|null */
    public ?array $freeTextAnswers;

    /**
     * @param array<int, array{choice_id: string, label: string, count: int}>|null $choiceCounts
     * @param array<int, int>|null $ratingCounts
     * @param string[]|null $freeTextAnswers
     */
    public function __construct(
        string $questionId,
        string $text,
        string $type,
        ?array $choiceCounts = null,
        ?array $ratingCounts = null,
        ?float $ratingAverage = null,
        ?int $yesCount = null,
        ?int $noCount = null,
        ?array $freeTextAnswers = null
    ) {
        $this->questionId = $questionId;
        $this->text = $text;
        $this->type = $type;
        $this->choiceCounts = $choiceCounts;
        $this->ratingCounts = $ratingCounts;
        $this->ratingAverage = $ratingAverage;
        $this->yesCount = $yesCount;
        $this->noCount = $noCount;
        $this->freeTextAnswers = $freeTextAnswers;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'question_id' => $this->questionId,
            'text' => $this->text,
            'type' => $this->type,
            'choice_counts' => $this->choiceCounts,
            'rating_counts' => $this->ratingCounts,
            'rating_average' => $this->ratingAverage,
            'yes_count' => $this->yesCount,
            'no_count' => $this->noCount,
            'free_text_answers' => $this->freeTextAnswers,
        ];
    }
}
