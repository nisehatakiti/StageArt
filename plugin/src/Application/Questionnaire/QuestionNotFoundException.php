<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use RuntimeException;

final class QuestionNotFoundException extends RuntimeException
{
    public function __construct(string $questionId)
    {
        parent::__construct("Question not found: {$questionId}");
    }
}
