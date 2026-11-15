<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

final class SubmitQuestionnaireResponseCommand
{
    public string $productionSlug;
    /**
     * @var array<int, array{question_id: string, value: mixed}>
     */
    public array $answers;

    /**
     * @param array<int, array{question_id: string, value: mixed}> $answers
     */
    public function __construct(string $productionSlug, array $answers)
    {
        $this->productionSlug = $productionSlug;
        $this->answers = $answers;
    }
}
