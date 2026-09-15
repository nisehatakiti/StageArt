<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use StageArt\Domain\Questionnaire\Question;
use StageArt\Domain\Questionnaire\QuestionnaireResponseRepositoryInterface;

/**
 * §8/§49 ("既存回答の意味を壊す変更を禁止する"), kept out of Question::class
 * itself the same way ReservationModificationPolicy keeps cross-Aggregate
 * temporal policy out of Reservation - this is the one place that decides
 * whether a specific edit/delete is *currently* allowed, by asking
 * QuestionnaireResponseRepositoryInterface whether the Question already
 * has at least one answer.
 *
 * V1's constraint (§8's own closing line) is intentionally the simplest
 * option Blueprint offers ("編集不可 / 削除不可 / 新しいQuestionとして作り直す")
 * rather than a versioning scheme: once answered, a Question may still
 * have its text/required flag updated and new choices appended (neither
 * breaks how an existing answer is interpreted), but it can never be
 * deleted, and no existing choice id may be removed.
 */
final class QuestionEditPolicy
{
    private QuestionnaireResponseRepositoryInterface $responses;

    public function __construct(QuestionnaireResponseRepositoryInterface $responses)
    {
        $this->responses = $responses;
    }

    public function assertDeletable(Question $question): void
    {
        if ($this->responses->existsAnswerForQuestion($question->id())) {
            throw new QuestionEditLockedException(
                'This Question already has Responses and cannot be deleted.'
            );
        }
    }

    /**
     * @param string[] $newChoiceIds
     */
    public function assertChoicesStillCoverExisting(Question $question, array $newChoiceIds): void
    {
        if (! $this->responses->existsAnswerForQuestion($question->id())) {
            return;
        }

        foreach ($question->choices() as $existingChoice) {
            if (! in_array($existingChoice->id(), $newChoiceIds, true)) {
                throw new QuestionEditLockedException(
                    'This Question already has Responses - an existing choice cannot be removed or reused.'
                );
            }
        }
    }
}
