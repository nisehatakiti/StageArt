<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use StageArt\Domain\Questionnaire\Question;

/**
 * §35: the public GET response only ever carries what a respondent needs
 * to render and answer the form (id/text/type/required/choices) - no
 * timestamps, no internal metadata.
 */
final class PublicQuestionResult
{
    public string $id;
    public string $text;
    public string $type;
    public bool $required;
    public int $displayOrder;
    /** @var array<int, array{id: string, label: string}> */
    public array $choices;

    /**
     * @param array<int, array{id: string, label: string}> $choices
     */
    private function __construct(string $id, string $text, string $type, bool $required, int $displayOrder, array $choices)
    {
        $this->id = $id;
        $this->text = $text;
        $this->type = $type;
        $this->required = $required;
        $this->displayOrder = $displayOrder;
        $this->choices = $choices;
    }

    public static function fromDomain(Question $question): self
    {
        return new self(
            $question->id()->toString(),
            $question->text(),
            $question->type()->toString(),
            $question->required(),
            $question->displayOrder(),
            array_map(static fn ($choice) => ['id' => $choice->id(), 'label' => $choice->label()], $question->choices())
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'type' => $this->type,
            'required' => $this->required,
            'display_order' => $this->displayOrder,
            'choices' => $this->choices,
        ];
    }
}
