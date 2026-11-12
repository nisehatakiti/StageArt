<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

final class QuestionnaireResultsResult
{
    public string $questionnaireId;
    public int $totalResponses;
    /** @var QuestionAggregateResult[] */
    public array $questions;

    /**
     * @param QuestionAggregateResult[] $questions
     */
    public function __construct(string $questionnaireId, int $totalResponses, array $questions)
    {
        $this->questionnaireId = $questionnaireId;
        $this->totalResponses = $totalResponses;
        $this->questions = $questions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'questionnaire_id' => $this->questionnaireId,
            'total_responses' => $this->totalResponses,
            'questions' => array_map(static fn (QuestionAggregateResult $question) => $question->toArray(), $this->questions),
        ];
    }
}
