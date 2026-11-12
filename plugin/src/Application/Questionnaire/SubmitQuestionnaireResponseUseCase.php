<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Questionnaire\Question;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionnaireResponse;
use StageArt\Domain\Questionnaire\QuestionnaireResponseRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionType;
use StageArt\Domain\Questionnaire\ResponseAnswer;

/**
 * §16/§17/§43/§50: the public, unauthenticated Submit path. Deliberately
 * accepts nothing from the Request beyond `productionSlug` (URL) and
 * `answers` (body) - no reservation id, no email, no token, no respondent
 * identity of any kind (§35's own "Request bodyにはReservation IDやEmailな
 * どを受け取る設計にしない"), and everything it hands to
 * QuestionnaireResponse::submit() is exactly what §10's ban list allows.
 */
final class SubmitQuestionnaireResponseUseCase
{
    private QuestionnaireRepositoryInterface $questionnaires;
    private QuestionRepositoryInterface $questions;
    private QuestionnaireResponseRepositoryInterface $responses;

    public function __construct(
        QuestionnaireRepositoryInterface $questionnaires,
        QuestionRepositoryInterface $questions,
        QuestionnaireResponseRepositoryInterface $responses
    ) {
        $this->questionnaires = $questionnaires;
        $this->questions = $questions;
        $this->responses = $responses;
    }

    public function execute(SubmitQuestionnaireResponseCommand $command): void
    {
        $questionnaire = $this->questionnaires->findByProductionSlug($command->productionSlug);

        if ($questionnaire === null) {
            throw new QuestionnaireNotFoundException($command->productionSlug);
        }

        if (! $questionnaire->isAcceptingResponses(new DateTimeImmutable())) {
            throw new QuestionnaireNotAcceptingResponsesException(
                'This Questionnaire is not currently accepting responses.'
            );
        }

        $questions = $this->questions->findByQuestionnaireId($questionnaire->id());
        $questionsById = [];
        foreach ($questions as $question) {
            $questionsById[$question->id()->toString()] = $question;
        }

        $submittedByQuestionId = [];
        foreach ($command->answers as $answerInput) {
            $questionId = (string) ($answerInput['question_id'] ?? '');

            if (! isset($questionsById[$questionId])) {
                throw new InvalidArgumentException("Unknown question_id: {$questionId}");
            }

            $submittedByQuestionId[$questionId] = $answerInput['value'] ?? null;
        }

        $answers = [];
        foreach ($questions as $question) {
            $questionId = $question->id()->toString();
            $value = $submittedByQuestionId[$questionId] ?? null;
            $isBlank = $value === null || $value === '' || $value === [];

            if ($question->required() && $isBlank) {
                throw new InvalidArgumentException("Question {$questionId} is required.");
            }

            if ($isBlank) {
                continue;
            }

            $answers[] = ResponseAnswer::create($questionId, $this->normalizeValue($question, $value));
        }

        $response = QuestionnaireResponse::submit($questionnaire->id(), $answers);

        $this->responses->save($response);
    }

    /**
     * @param mixed $value
     * @return string|int|string[]
     */
    private function normalizeValue(Question $question, $value)
    {
        $type = $question->type()->toString();

        switch ($type) {
            case QuestionType::SINGLE_CHOICE:
                $choiceId = (string) $value;
                if ($question->findChoice($choiceId) === null) {
                    throw new InvalidArgumentException("Invalid choice for question {$question->id()->toString()}: {$choiceId}");
                }

                return $choiceId;

            case QuestionType::MULTIPLE_CHOICE:
                if (! is_array($value)) {
                    throw new InvalidArgumentException("Question {$question->id()->toString()} expects a list of choice ids.");
                }

                $choiceIds = [];
                foreach ($value as $choiceId) {
                    $choiceId = (string) $choiceId;
                    if ($question->findChoice($choiceId) === null) {
                        throw new InvalidArgumentException("Invalid choice for question {$question->id()->toString()}: {$choiceId}");
                    }
                    $choiceIds[] = $choiceId;
                }

                return $choiceIds;

            case QuestionType::RATING_5:
                $rating = (int) $value;
                if ($rating < 1 || $rating > 5) {
                    throw new InvalidArgumentException("Question {$question->id()->toString()} expects a rating between 1 and 5.");
                }

                return $rating;

            case QuestionType::YES_NO:
                $answer = strtoupper((string) $value);
                if (! in_array($answer, ['YES', 'NO'], true)) {
                    throw new InvalidArgumentException("Question {$question->id()->toString()} expects YES or NO.");
                }

                return $answer;

            default:
                return trim((string) $value);
        }
    }
}
