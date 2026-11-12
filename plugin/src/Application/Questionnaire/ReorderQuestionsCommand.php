<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

final class ReorderQuestionsCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    /** @var string[] QuestionId strings in the desired display order */
    public array $orderedQuestionIds;

    /**
     * @param string[] $orderedQuestionIds
     */
    public function __construct(string $productionId, int $requestedByWordPressUserId, array $orderedQuestionIds)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->orderedQuestionIds = $orderedQuestionIds;
    }
}
