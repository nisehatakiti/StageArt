<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use InvalidArgumentException;
use StageArt\Domain\Shared\Uuid;

/**
 * §8: "選択肢IDを別の意味に再利用" is explicitly forbidden once a Question has
 * Responses - `id` is generated once at first creation and never reassigned
 * to a different label thereafter (QuestionEditPolicy in the Application
 * layer enforces the "not reused for a different meaning" rule; this VO
 * only carries the id/label/displayOrder shape).
 */
final class QuestionChoice
{
    private string $id;
    private string $label;
    private int $displayOrder;

    private function __construct(string $id, string $label, int $displayOrder)
    {
        if (! Uuid::isValid($id)) {
            throw new InvalidArgumentException("Invalid QuestionChoice id: {$id}");
        }

        $trimmed = trim($label);

        if ($trimmed === '') {
            throw new InvalidArgumentException('QuestionChoice label must not be empty.');
        }

        $this->id = $id;
        $this->label = $trimmed;
        $this->displayOrder = $displayOrder;
    }

    public static function create(string $label, int $displayOrder): self
    {
        return new self(Uuid::generate(), $label, $displayOrder);
    }

    public static function reconstitute(string $id, string $label, int $displayOrder): self
    {
        return new self($id, $label, $displayOrder);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    /**
     * @return array{id: string, label: string, display_order: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'display_order' => $this->displayOrder,
        ];
    }
}
