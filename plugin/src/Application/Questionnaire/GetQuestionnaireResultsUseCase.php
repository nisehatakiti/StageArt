<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Questionnaire\Question;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionnaireResponse;
use StageArt\Domain\Questionnaire\QuestionnaireResponseRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionType;

/**
 * §37/§38/§39: reads every raw QuestionnaireResponse for this Questionnaire
 * and tallies in PHP - no analytics infrastructure, no join against
 * Reservation/Person of any kind (there is nothing on QuestionnaireResponse
 * to join against in the first place - see that class's own docblock).
 */
final class GetQuestionnaireResultsUseCase
{
    private QuestionnaireRepositoryInterface $questionnaires;
    private QuestionRepositoryInterface $questions;
    private QuestionnaireResponseRepositoryInterface $responses;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        QuestionnaireRepositoryInterface $questionnaires,
        QuestionRepositoryInterface $questions,
        QuestionnaireResponseRepositoryInterface $responses,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->questionnaires = $questionnaires;
        $this->questions = $questions;
        $this->responses = $responses;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(GetQuestionnaireResultsQuery $query): QuestionnaireResultsResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new QuestionnaireAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, QuestionnaireCapability::MANAGE)) {
            throw new QuestionnaireAccessDeniedException(
                'Only the PrimaryManager or an authorized ProductionDelegate can view this Production\'s Questionnaire results.'
            );
        }

        $questionnaire = $this->questionnaires->findByProductionId($productionId);

        if ($questionnaire === null) {
            throw new QuestionnaireNotFoundException($query->productionId);
        }

        $questions = $this->questions->findByQuestionnaireId($questionnaire->id());
        $responses = $this->responses->findByQuestionnaireId($questionnaire->id());

        $questionResults = [];
        foreach ($questions as $question) {
            $questionResults[] = $this->aggregateForQuestion($question, $responses);
        }

        return new QuestionnaireResultsResult($questionnaire->id()->toString(), count($responses), $questionResults);
    }

    /**
     * @param QuestionnaireResponse[] $responses
     */
    private function aggregateForQuestion(Question $question, array $responses): QuestionAggregateResult
    {
        $questionId = $question->id()->toString();
        $type = $question->type()->toString();

        $values = [];
        foreach ($responses as $response) {
            foreach ($response->answers() as $answer) {
                if ($answer->questionId() === $questionId) {
                    $values[] = $answer->value();
                }
            }
        }

        switch ($type) {
            case QuestionType::SINGLE_CHOICE:
                $counts = $this->emptyChoiceCounts($question);
                foreach ($values as $value) {
                    if (is_string($value) && isset($counts[$value])) {
                        $counts[$value]['count']++;
                    }
                }

                return new QuestionAggregateResult($questionId, $question->text(), $type, array_values($counts));

            case QuestionType::MULTIPLE_CHOICE:
                $counts = $this->emptyChoiceCounts($question);
                foreach ($values as $value) {
                    if (! is_array($value)) {
                        continue;
                    }
                    foreach ($value as $choiceId) {
                        if (isset($counts[$choiceId])) {
                            $counts[$choiceId]['count']++;
                        }
                    }
                }

                return new QuestionAggregateResult($questionId, $question->text(), $type, array_values($counts));

            case QuestionType::RATING_5:
                $ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
                $sum = 0;
                $count = 0;
                foreach ($values as $value) {
                    $rating = (int) $value;
                    if ($rating >= 1 && $rating <= 5) {
                        $ratingCounts[$rating]++;
                        $sum += $rating;
                        $count++;
                    }
                }
                $average = $count > 0 ? round($sum / $count, 2) : null;

                return new QuestionAggregateResult($questionId, $question->text(), $type, null, $ratingCounts, $average);

            case QuestionType::YES_NO:
                $yes = 0;
                $no = 0;
                foreach ($values as $value) {
                    if ($value === 'YES') {
                        $yes++;
                    } elseif ($value === 'NO') {
                        $no++;
                    }
                }

                return new QuestionAggregateResult($questionId, $question->text(), $type, null, null, null, $yes, $no);

            default:
                $texts = array_values(array_filter(array_map(
                    static fn ($value) => is_string($value) ? $value : null,
                    $values
                )));

                return new QuestionAggregateResult($questionId, $question->text(), $type, null, null, null, null, null, $texts);
        }
    }

    /**
     * @return array<string, array{choice_id: string, label: string, count: int}>
     */
    private function emptyChoiceCounts(Question $question): array
    {
        $counts = [];
        foreach ($question->choices() as $choice) {
            $counts[$choice->id()] = ['choice_id' => $choice->id(), 'label' => $choice->label(), 'count' => 0];
        }

        return $counts;
    }
}
