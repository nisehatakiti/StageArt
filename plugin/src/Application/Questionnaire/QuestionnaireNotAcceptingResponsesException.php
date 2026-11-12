<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use RuntimeException;

/**
 * §16/§18/§48: thrown for a DRAFT/CLOSED Questionnaire, or a PUBLISHED one
 * past its own `responseEndAt` - Backend-side enforcement so a Frontend
 * that skips its own required/deadline checks can never actually submit
 * (§16 - "Frontendだけで必須チェックを完結させてはいけない" extends to every
 * other Backend-side validation this class enforces too).
 */
final class QuestionnaireNotAcceptingResponsesException extends RuntimeException
{
}
