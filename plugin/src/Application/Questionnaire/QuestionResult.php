<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use StageArt\Domain\Questionnaire\Question;

final class QuestionResult
{
    public string $id;
    public string $questionnaireId;
    public string $text;
    public string $type;
    public bool $required;
    public int $displayOrder;
    /** @var array<int, array{id: string, label: string, display_order: int}> */
    public array $choices;
    public string $createdAt;
    public string $updatedAt;

    /**
     * @param array<int, array{id: string, label: string, display_order: int}> $choices
     */
    private function __construct(
        string $id,
        string $questionnaireId,
        string $text,
        string $type,
        bool $required,
        int $displayOrder,
        array $choices,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->questionnaireId = $questionnaireId;
        $this->text = $text;
        $this->type = $type;
        $this->required = $required;
        $this->displayOrder = $displayOrder;
        $this->choices = $choices;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(Question $question): self
    {
        return new self(
            $question->id()->toString(),
            $question->questionnaireId()->toString(),
            $question->text(),
            $question->type()->toString(),
            $question->required(),
            $question->displayOrder(),
            array_map(static fn ($choice) => $choice->toArray(), $question->choices()),
            $question->createdAt()->format(DATE_ATOM),
            $question->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'questionnaire_id' => $this->questionnaireId,
            'text' => $this->text,
            'type' => $this->type,
            'required' => $this->required,
            'display_order' => $this->displayOrder,
            'choices' => $this->choices,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
