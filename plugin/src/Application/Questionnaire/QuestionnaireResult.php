<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use StageArt\Domain\Questionnaire\Questionnaire;

final class QuestionnaireResult
{
    public string $id;
    public string $productionId;
    public string $title;
    public ?string $description;
    public string $status;
    public ?string $responseEndAt;
    public string $createdAt;
    public string $updatedAt;
    public string $publicUrl;
    /** @var QuestionResult[] */
    public array $questions;

    /**
     * @param QuestionResult[] $questions
     */
    private function __construct(
        string $id,
        string $productionId,
        string $title,
        ?string $description,
        string $status,
        ?string $responseEndAt,
        string $createdAt,
        string $updatedAt,
        string $publicUrl,
        array $questions
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
        $this->responseEndAt = $responseEndAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->publicUrl = $publicUrl;
        $this->questions = $questions;
    }

    /**
     * @param QuestionResult[] $questions
     */
    public static function fromDomain(Questionnaire $questionnaire, string $publicUrl, array $questions): self
    {
        return new self(
            $questionnaire->id()->toString(),
            $questionnaire->productionId()->toString(),
            $questionnaire->title(),
            $questionnaire->description(),
            $questionnaire->status()->toString(),
            $questionnaire->responseEndAt()?->format(DATE_ATOM),
            $questionnaire->createdAt()->format(DATE_ATOM),
            $questionnaire->updatedAt()->format(DATE_ATOM),
            $publicUrl,
            $questions
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'production_id' => $this->productionId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'response_end_at' => $this->responseEndAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'public_url' => $this->publicUrl,
            'questions' => array_map(static fn (QuestionResult $question) => $question->toArray(), $this->questions),
        ];
    }
}
