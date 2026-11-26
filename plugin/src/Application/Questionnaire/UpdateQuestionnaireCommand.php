<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

final class UpdateQuestionnaireCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $title;
    public ?string $description;
    public ?string $responseEndAt;

    public function __construct(string $productionId, int $requestedByWordPressUserId, string $title, ?string $description, ?string $responseEndAt)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->title = $title;
        $this->description = $description;
        $this->responseEndAt = $responseEndAt;
    }
}
