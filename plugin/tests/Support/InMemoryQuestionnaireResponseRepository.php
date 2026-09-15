<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Questionnaire\QuestionId;
use StageArt\Domain\Questionnaire\QuestionnaireId;
use StageArt\Domain\Questionnaire\QuestionnaireResponse;
use StageArt\Domain\Questionnaire\QuestionnaireResponseRepositoryInterface;

final class InMemoryQuestionnaireResponseRepository implements QuestionnaireResponseRepositoryInterface
{
    /** @var QuestionnaireResponse[] */
    private array $responses = [];

    public function save(QuestionnaireResponse $response): void
    {
        $this->responses[] = $response;
    }

    public function findByQuestionnaireId(QuestionnaireId $questionnaireId): array
    {
        return array_values(array_filter(
            $this->responses,
            static fn (QuestionnaireResponse $response): bool => $response->questionnaireId()->equals($questionnaireId)
        ));
    }

    public function countByQuestionnaireId(QuestionnaireId $questionnaireId): int
    {
        return count($this->findByQuestionnaireId($questionnaireId));
    }

    public function existsAnswerForQuestion(QuestionId $questionId): bool
    {
        foreach ($this->responses as $response) {
            foreach ($response->answers() as $answer) {
                if ($answer->questionId() === $questionId->toString()) {
                    return true;
                }
            }
        }

        return false;
    }
}
