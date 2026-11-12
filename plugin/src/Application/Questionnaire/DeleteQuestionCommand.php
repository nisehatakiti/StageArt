<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

final class DeleteQuestionCommand
{
    public string $productionId;
    public string $questionId;
    public int $requestedByWordPressUserId;

    public function __construct(string $productionId, string $questionId, int $requestedByWordPressUserId)
    {
        $this->productionId = $productionId;
        $this->questionId = $questionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
