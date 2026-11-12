<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use StageArt\Domain\Production\ProductionId;

interface QuestionnaireRepositoryInterface
{
    public function save(Questionnaire $questionnaire): void;

    public function findById(QuestionnaireId $id): ?Questionnaire;

    /**
     * §3/§31: 1 Production : 1 Questionnaire in V1 - this is the lookup
     * CreateQuestionnaireUseCase uses to enforce "2つ目を作成できない".
     */
    public function findByProductionId(ProductionId $productionId): ?Questionnaire;

    /**
     * §5/§35: the public, unauthenticated read path resolves a
     * Questionnaire from a Production slug, not a Questionnaire id or any
     * per-recipient token - see GetPublicQuestionnaireUseCase.
     */
    public function findByProductionSlug(string $productionSlug): ?Questionnaire;
}
