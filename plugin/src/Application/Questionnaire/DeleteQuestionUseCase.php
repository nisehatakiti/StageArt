<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Questionnaire\QuestionId;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionRepositoryInterface;

final class DeleteQuestionUseCase
{
    private QuestionnaireRepositoryInterface $questionnaires;
    private QuestionRepositoryInterface $questions;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private QuestionEditPolicy $editPolicy;

    public function __construct(
        QuestionnaireRepositoryInterface $questionnaires,
        QuestionRepositoryInterface $questions,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        QuestionEditPolicy $editPolicy
    ) {
        $this->questionnaires = $questionnaires;
        $this->questions = $questions;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->editPolicy = $editPolicy;
    }

    public function execute(DeleteQuestionCommand $command): void
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new QuestionnaireAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, QuestionnaireCapability::MANAGE)) {
            throw new QuestionnaireAccessDeniedException(
                'Only the PrimaryManager or an authorized ProductionDelegate can delete Questions from this Production\'s Questionnaire.'
            );
        }

        $questionnaire = $this->questionnaires->findByProductionId($productionId);

        if ($questionnaire === null) {
            throw new QuestionnaireNotFoundException($command->productionId);
        }

        $question = $this->questions->findById(QuestionId::fromString($command->questionId));

        if ($question === null || ! $question->questionnaireId()->equals($questionnaire->id())) {
            throw new QuestionNotFoundException($command->questionId);
        }

        $this->editPolicy->assertDeletable($question);

        $this->questions->delete($question->id());
    }
}
