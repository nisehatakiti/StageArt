<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use DateTimeImmutable;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionnaireStatus;
use StageArt\Domain\Questionnaire\QuestionRepositoryInterface;

/**
 * §5/§14/§16/§45: no permission check by design - the public,
 * unauthenticated `stageart.top/{organization-slug}/{production-slug}/
 * questionnaire` read path, matching GetPublicProductionBySlugUseCase's
 * own precedent. A DRAFT Questionnaire is treated exactly like "no
 * Questionnaire" (§45 - "公開されていないアンケートへ回答依頼を送らない" extends
 * to browsing too, matching the Production-level "not-found and
 * exists-but-unpublished both throw the identical NotFoundException"
 * precedent).
 */
final class GetPublicQuestionnaireUseCase
{
    private QuestionnaireRepositoryInterface $questionnaires;
    private QuestionRepositoryInterface $questions;
    private ProductionContextContract $productionContext;

    public function __construct(
        QuestionnaireRepositoryInterface $questionnaires,
        QuestionRepositoryInterface $questions,
        ProductionContextContract $productionContext
    ) {
        $this->questionnaires = $questionnaires;
        $this->questions = $questions;
        $this->productionContext = $productionContext;
    }

    public function execute(GetPublicQuestionnaireQuery $query): PublicQuestionnaireResult
    {
        $questionnaire = $this->questionnaires->findByProductionSlug($query->productionSlug);

        if ($questionnaire === null || $questionnaire->status()->equals(QuestionnaireStatus::fromString(QuestionnaireStatus::DRAFT))) {
            throw new QuestionnaireNotFoundException($query->productionSlug);
        }

        $production = $this->productionContext->getProduction($questionnaire->productionId());

        if ($production === null) {
            throw new QuestionnaireNotFoundException($query->productionSlug);
        }

        $questions = $this->questions->findByQuestionnaireId($questionnaire->id());

        return PublicQuestionnaireResult::fromDomain(
            $production->name,
            $questionnaire,
            $questionnaire->isAcceptingResponses(new DateTimeImmutable()),
            array_map(static fn ($question) => PublicQuestionResult::fromDomain($question), $questions)
        );
    }
}
