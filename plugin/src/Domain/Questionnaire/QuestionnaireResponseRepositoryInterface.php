<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

interface QuestionnaireResponseRepositoryInterface
{
    public function save(QuestionnaireResponse $response): void;

    /**
     * §37/§39: aggregation reads every raw Response for a Questionnaire -
     * there is no narrower "by Question" query, since a single Response's
     * answers all arrive together and the caller (GetQuestionnaireResultsUseCase)
     * tallies per-Question itself.
     *
     * @return QuestionnaireResponse[]
     */
    public function findByQuestionnaireId(QuestionnaireId $questionnaireId): array;

    public function countByQuestionnaireId(QuestionnaireId $questionnaireId): int;

    /**
     * §8/§49: QuestionEditPolicy calls this to decide whether a Question
     * already has at least one answer, before allowing a
     * meaning-breaking edit or a delete.
     */
    public function existsAnswerForQuestion(QuestionId $questionId): bool;
}
