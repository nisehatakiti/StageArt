<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

final class UpdateQuestionCommand
{
    public string $productionId;
    public string $questionId;
    public int $requestedByWordPressUserId;
    public string $text;
    public bool $required;
    /** @var array<int, array{id: ?string, label: string}> */
    public array $choices;

    /**
     * @param array<int, array{id: ?string, label: string}> $choices
     */
    public function __construct(string $productionId, string $questionId, int $requestedByWordPressUserId, string $text, bool $required, array $choices)
    {
        $this->productionId = $productionId;
        $this->questionId = $questionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->text = $text;
        $this->required = $required;
        $this->choices = $choices;
    }
}
