<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use RuntimeException;

final class QuestionnaireNotFoundException extends RuntimeException
{
    public function __construct(string $identifier)
    {
        parent::__construct("Questionnaire not found: {$identifier}");
    }
}
