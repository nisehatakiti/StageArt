<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

final class AddQuestionCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $text;
    public string $type;
    public bool $required;
    /** @var string[] */
    public array $choiceLabels;

    /**
     * @param string[] $choiceLabels
     */
    public function __construct(string $productionId, int $requestedByWordPressUserId, string $text, string $type, bool $required, array $choiceLabels)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->text = $text;
        $this->type = $type;
        $this->required = $required;
        $this->choiceLabels = $choiceLabels;
    }
}
