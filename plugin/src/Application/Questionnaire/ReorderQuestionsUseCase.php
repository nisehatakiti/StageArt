<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionRepositoryInterface;

final class ReorderQuestionsUseCase
{
    private QuestionnaireRepositoryInterface $questionnaires;
    private QuestionRepositoryInterface $questions;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        QuestionnaireRepositoryInterface $questionnaires,
        QuestionRepositoryInterface $questions,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->questionnaires = $questionnaires;
        $this->questions = $questions;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    /**
     * @return QuestionResult[]
     */
    public function execute(ReorderQuestionsCommand $command): array
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
                'Only the PrimaryManager or an authorized ProductionDelegate can reorder this Production\'s Questions.'
            );
        }

        $questionnaire = $this->questionnaires->findByProductionId($productionId);

        if ($questionnaire === null) {
            throw new QuestionnaireNotFoundException($command->productionId);
        }

        $existingQuestions = $this->questions->findByQuestionnaireId($questionnaire->id());
        $existingById = [];
        foreach ($existingQuestions as $existingQuestion) {
            $existingById[$existingQuestion->id()->toString()] = $existingQuestion;
        }

        if (count($command->orderedQuestionIds) !== count($existingById)
            || array_diff($command->orderedQuestionIds, array_keys($existingById)) !== []
        ) {
            throw new InvalidArgumentException('orderedQuestionIds must contain exactly this Questionnaire\'s current Question ids.');
        }

        $reordered = [];
        foreach ($command->orderedQuestionIds as $displayOrder => $questionId) {
            $question = $existingById[$questionId];
            $question->changeDisplayOrder($displayOrder);
            $this->questions->save($question);
            $reordered[] = QuestionResult::fromDomain($question);
        }

        return $reordered;
    }
}
