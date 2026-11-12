<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use RuntimeException;

/**
 * §3/§31: V1では1 ProductionにつきQuestionnaireは1つまで。
 */
final class QuestionnaireAlreadyExistsException extends RuntimeException
{
    public function __construct(string $productionId)
    {
        parent::__construct("Production {$productionId} already has a Questionnaire.");
    }
}
