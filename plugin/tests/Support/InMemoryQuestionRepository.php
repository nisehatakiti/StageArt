<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Questionnaire\Question;
use StageArt\Domain\Questionnaire\QuestionId;
use StageArt\Domain\Questionnaire\QuestionnaireId;
use StageArt\Domain\Questionnaire\QuestionRepositoryInterface;

final class InMemoryQuestionRepository implements QuestionRepositoryInterface
{
    /** @var array<string, Question> */
    private array $questions = [];

    public function save(Question $question): void
    {
        $this->questions[$question->id()->toString()] = $question;
    }

    public function findById(QuestionId $id): ?Question
    {
        return $this->questions[$id->toString()] ?? null;
    }

    public function delete(QuestionId $id): void
    {
        unset($this->questions[$id->toString()]);
    }

    public function findByQuestionnaireId(QuestionnaireId $questionnaireId): array
    {
        $matches = array_values(array_filter(
            $this->questions,
            static fn (Question $question): bool => $question->questionnaireId()->equals($questionnaireId)
        ));

        usort($matches, static fn (Question $a, Question $b): int => $a->displayOrder() <=> $b->displayOrder());

        return $matches;
    }
}
