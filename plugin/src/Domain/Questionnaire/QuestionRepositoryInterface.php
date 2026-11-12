<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

interface QuestionRepositoryInterface
{
    public function save(Question $question): void;

    public function findById(QuestionId $id): ?Question;

    public function delete(QuestionId $id): void;

    /**
     * @return Question[] ordered by displayOrder ascending
     */
    public function findByQuestionnaireId(QuestionnaireId $questionnaireId): array;
}
