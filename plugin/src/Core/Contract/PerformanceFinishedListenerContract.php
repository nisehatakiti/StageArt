<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Performance\PerformanceId;

/**
 * アンケート実装指示書 §20/§21/§42: the Performance Module has no reason to
 * know the Questionnaire Module exists (StageArt Core/Module Architecture -
 * see PerformanceCapability's own docblock for the same "a Module never
 * hardcodes another Module's name" rule). This Contract is the one
 * narrow, generic hook UpdatePerformanceUseCase calls when a Performance's
 * Status transitions into FINISHED, so a Module that cares (today, only
 * Questionnaire) can react without Performance depending on it.
 *
 * §42's "メール送信失敗によってPerformance終了そのものが失敗する設計は禁止" is
 * satisfied by UpdatePerformanceUseCase invoking this only *after* the
 * Performance Status change has already been persisted, and by every
 * known implementation of this Contract never letting an Exception escape
 * (see QuestionnairePerformanceFinishedListener's own docblock) - but
 * UpdatePerformanceUseCase itself does not rely on that alone: it also
 * wraps the call in its own try/catch, so even a future, less careful
 * implementation cannot turn a Performance update into a failed request.
 */
interface PerformanceFinishedListenerContract
{
    public function onPerformanceFinished(PerformanceId $performanceId): void;
}
