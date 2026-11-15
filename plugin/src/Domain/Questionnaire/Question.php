<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * アンケート実装指示書 §6/§8: a single Question belonging to one
 * Questionnaire. `type` is fixed at creation (see QuestionType::class's own
 * docblock) - `updateContent()` only ever touches text/required/
 * displayOrder/choices. Whether a *specific* content change is currently
 * safe (i.e. no existing QuestionnaireResponse answers this Question yet)
 * is a cross-Aggregate check the Application layer's QuestionEditPolicy
 * makes before calling any mutator here - matching
 * ReservationModificationPolicy's precedent of keeping temporal/
 * cross-Aggregate policy out of the Entity itself.
 */
final class Question
{
    private QuestionId $id;
    private QuestionnaireId $questionnaireId;
    private string $text;
    private QuestionType $type;
    private bool $required;
    private int $displayOrder;
    /** @var QuestionChoice[] */
    private array $choices;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    /**
     * @param QuestionChoice[] $choices
     */
    private function __construct(
        QuestionId $id,
        QuestionnaireId $questionnaireId,
        string $text,
        QuestionType $type,
        bool $required,
        int $displayOrder,
        array $choices,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->questionnaireId = $questionnaireId;
        $this->text = $text;
        $this->type = $type;
        $this->required = $required;
        $this->displayOrder = $displayOrder;
        $this->choices = self::validateChoices($type, $choices);
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param QuestionChoice[] $choices
     */
    public static function create(
        QuestionnaireId $questionnaireId,
        string $text,
        QuestionType $type,
        bool $required,
        int $displayOrder,
        array $choices
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            QuestionId::generate(),
            $questionnaireId,
            self::validateText($text),
            $type,
            $required,
            $displayOrder,
            $choices,
            $now,
            $now
        );
    }

    /**
     * @param QuestionChoice[] $choices
     */
    public static function reconstitute(
        QuestionId $id,
        QuestionnaireId $questionnaireId,
        string $text,
        QuestionType $type,
        bool $required,
        int $displayOrder,
        array $choices,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $questionnaireId, $text, $type, $required, $displayOrder, $choices, $createdAt, $updatedAt);
    }

    /**
     * @param QuestionChoice[] $choices
     */
    public function updateContent(string $text, bool $required, array $choices): void
    {
        $this->text = self::validateText($text);
        $this->required = $required;
        $this->choices = self::validateChoices($this->type, $choices);
        $this->touch();
    }

    public function changeDisplayOrder(int $displayOrder): void
    {
        $this->displayOrder = $displayOrder;
        $this->touch();
    }

    /**
     * @param QuestionChoice[] $choices
     * @return QuestionChoice[]
     */
    private static function validateChoices(QuestionType $type, array $choices): array
    {
        foreach ($choices as $choice) {
            if (! $choice instanceof QuestionChoice) {
                throw new InvalidArgumentException('Every choice must be a QuestionChoice.');
            }
        }

        if ($type->isChoiceBased() && $choices === []) {
            throw new InvalidArgumentException('SINGLE_CHOICE/MULTIPLE_CHOICE questions require at least one choice.');
        }

        return array_values($choices);
    }

    private static function validateText(string $text): string
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Question text must not be empty.');
        }

        return $trimmed;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): QuestionId
    {
        return $this->id;
    }

    public function questionnaireId(): QuestionnaireId
    {
        return $this->questionnaireId;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function type(): QuestionType
    {
        return $this->type;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    /**
     * @return QuestionChoice[]
     */
    public function choices(): array
    {
        return $this->choices;
    }

    public function findChoice(string $choiceId): ?QuestionChoice
    {
        foreach ($this->choices as $choice) {
            if ($choice->id() === $choiceId) {
                return $choice;
            }
        }

        return null;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
