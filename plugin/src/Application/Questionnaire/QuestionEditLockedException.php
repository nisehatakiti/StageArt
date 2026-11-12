<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use RuntimeException;

/**
 * §8/§49: thrown when an edit would make an existing QuestionnaireResponse
 * unable to be interpreted correctly - a deletion, or a choice removal, on
 * a Question that already has at least one answer.
 */
final class QuestionEditLockedException extends RuntimeException
{
}
