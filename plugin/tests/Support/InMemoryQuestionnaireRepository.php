<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Questionnaire\Questionnaire;
use StageArt\Domain\Questionnaire\QuestionnaireId;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;

final class InMemoryQuestionnaireRepository implements QuestionnaireRepositoryInterface
{
    /** @var array<string, Questionnaire> */
    private array $questionnaires = [];

    /** @var array<string, string> QuestionnaireId => Production slug, set via registerSlug() in tests */
    private array $slugsByQuestionnaireId = [];

    public function save(Questionnaire $questionnaire): void
    {
        $this->questionnaires[$questionnaire->id()->toString()] = $questionnaire;
    }

    public function findById(QuestionnaireId $id): ?Questionnaire
    {
        return $this->questionnaires[$id->toString()] ?? null;
    }

    public function findByProductionId(ProductionId $productionId): ?Questionnaire
    {
        foreach ($this->questionnaires as $questionnaire) {
            if ($questionnaire->productionId()->equals($productionId)) {
                return $questionnaire;
            }
        }

        return null;
    }

    public function registerSlug(QuestionnaireId $questionnaireId, string $productionSlug): void
    {
        $this->slugsByQuestionnaireId[$questionnaireId->toString()] = $productionSlug;
    }

    public function findByProductionSlug(string $productionSlug): ?Questionnaire
    {
        foreach ($this->slugsByQuestionnaireId as $questionnaireId => $slug) {
            if ($slug === $productionSlug) {
                return $this->questionnaires[$questionnaireId] ?? null;
            }
        }

        return null;
    }
}
