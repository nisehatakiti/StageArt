<?php

declare(strict_types=1);

namespace StageArt\Questionnaire;

use StageArt\Application\Questionnaire\SendQuestionnaireInvitesForPerformanceUseCase;
use StageArt\Core\Contract\PerformanceFinishedListenerContract;
use StageArt\Domain\Performance\PerformanceId;

/**
 * The Questionnaire Module's own implementation of
 * PerformanceFinishedListenerContract - see that Contract's docblock for
 * why the Performance Module calls this without knowing it exists.
 */
final class QuestionnairePerformanceFinishedListener implements PerformanceFinishedListenerContract
{
    private SendQuestionnaireInvitesForPerformanceUseCase $sendInvites;

    public function __construct(SendQuestionnaireInvitesForPerformanceUseCase $sendInvites)
    {
        $this->sendInvites = $sendInvites;
    }

    public function onPerformanceFinished(PerformanceId $performanceId): void
    {
        $this->sendInvites->execute($performanceId);
    }
}
