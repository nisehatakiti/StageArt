<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Questionnaire\QuestionChoice;
use StageArt\Domain\Questionnaire\QuestionId;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionRepositoryInterface;

/**
 * §8/§49: routes every choice through QuestionEditPolicy before applying
 * it - an existing choice id that would disappear from the new list is
 * rejected outright once the Question has at least one Response.
 */
final class UpdateQuestionUseCase
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

    public function execute(UpdateQuestionCommand $command): QuestionResult
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
                'Only the PrimaryManager or an authorized ProductionDelegate can update this Production\'s Questionnaire.'
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

        $existingChoicesById = [];
        foreach ($question->choices() as $existingChoice) {
            $existingChoicesById[$existingChoice->id()] = $existingChoice;
        }

        $newChoices = [];
        $newChoiceIds = [];
        foreach ($command->choices as $index => $choiceInput) {
            $id = $choiceInput['id'] ?? null;
            $label = $choiceInput['label'];

            if ($id !== null && isset($existingChoicesById[$id])) {
                $choice = QuestionChoice::reconstitute($id, $label, $index);
            } elseif ($id !== null) {
                throw new InvalidArgumentException("Unknown choice id for this Question: {$id}");
            } else {
                $choice = QuestionChoice::create($label, $index);
            }

            $newChoices[] = $choice;
            $newChoiceIds[] = $choice->id();
        }

        $this->editPolicy->assertChoicesStillCoverExisting($question, $newChoiceIds);

        $question->updateContent($command->text, $command->required, $newChoices);

        $this->questions->save($question);

        return QuestionResult::fromDomain($question);
    }
}
