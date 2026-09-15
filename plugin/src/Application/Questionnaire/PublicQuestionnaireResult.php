<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use StageArt\Domain\Questionnaire\Questionnaire;

/**
 * §5/§14/§35/§36: the public, unauthenticated read shape - Production
 * name, Questionnaire title/description/status/questions, and whether it
 * is currently accepting responses. No Production id, no Questionnaire
 * internal timestamps, nothing that could resolve back to a Reservation or
 * Person (§36 - "Questionnaire公開APIは...閉じた設計にする").
 */
final class PublicQuestionnaireResult
{
    public string $productionName;
    public string $title;
    public ?string $description;
    public string $status;
    public bool $acceptingResponses;
    /** @var PublicQuestionResult[] */
    public array $questions;

    /**
     * @param PublicQuestionResult[] $questions
     */
    private function __construct(string $productionName, string $title, ?string $description, string $status, bool $acceptingResponses, array $questions)
    {
        $this->productionName = $productionName;
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
        $this->acceptingResponses = $acceptingResponses;
        $this->questions = $questions;
    }

    /**
     * @param PublicQuestionResult[] $questions
     */
    public static function fromDomain(string $productionName, Questionnaire $questionnaire, bool $acceptingResponses, array $questions): self
    {
        return new self(
            $productionName,
            $questionnaire->title(),
            $questionnaire->description(),
            $questionnaire->status()->toString(),
            $acceptingResponses,
            $questions
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'production_name' => $this->productionName,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'accepting_responses' => $this->acceptingResponses,
            'questions' => array_map(static fn (PublicQuestionResult $question) => $question->toArray(), $this->questions),
        ];
    }
}
