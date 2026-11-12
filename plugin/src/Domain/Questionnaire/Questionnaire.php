<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * アンケート実装指示書 §3/§4: Production所有、V1では1 Production : 1
 * Questionnaire (uniqueness is enforced by the Application layer/DB unique
 * key on production_id, not here - an Entity cannot see its siblings).
 *
 * §19: a Production becoming COMPLETED never touches this Entity - no
 * listener/cascade exists from Production lifecycle into Questionnaire, by
 * design (§45/§46/§47 instead key entirely off Questionnaire's own status
 * plus `responseEndAt` when deciding whether to send an invite Email).
 */
final class Questionnaire
{
    private QuestionnaireId $id;
    private ProductionId $productionId;
    private string $title;
    private ?string $description;
    private QuestionnaireStatus $status;
    private ?DateTimeImmutable $responseEndAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private ?PersonId $createdBy;
    private ?PersonId $updatedBy;

    private function __construct(
        QuestionnaireId $id,
        ProductionId $productionId,
        string $title,
        ?string $description,
        QuestionnaireStatus $status,
        ?DateTimeImmutable $responseEndAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?PersonId $createdBy,
        ?PersonId $updatedBy
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
        $this->responseEndAt = $responseEndAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->createdBy = $createdBy;
        $this->updatedBy = $updatedBy;
    }

    public static function create(
        ProductionId $productionId,
        string $title,
        ?string $description,
        ?PersonId $createdBy
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            QuestionnaireId::generate(),
            $productionId,
            self::validateTitle($title),
            self::normalizeNullableString($description),
            QuestionnaireStatus::draft(),
            null,
            $now,
            $now,
            $createdBy,
            $createdBy
        );
    }

    public static function reconstitute(
        QuestionnaireId $id,
        ProductionId $productionId,
        string $title,
        ?string $description,
        QuestionnaireStatus $status,
        ?DateTimeImmutable $responseEndAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?PersonId $createdBy,
        ?PersonId $updatedBy
    ): self {
        return new self(
            $id,
            $productionId,
            $title,
            $description,
            $status,
            $responseEndAt,
            $createdAt,
            $updatedAt,
            $createdBy,
            $updatedBy
        );
    }

    /**
     * §32/AC-04/AC-05/AC-47: title/description/responseEndAt stay editable
     * in both DRAFT and PUBLISHED (§4's DRAFT/PUBLISHED rules); CLOSED is
     * a read-only archive of what already happened.
     */
    public function updateDetails(string $title, ?string $description, ?DateTimeImmutable $responseEndAt, ?PersonId $updatedBy): void
    {
        if ($this->status->equals(QuestionnaireStatus::fromString(QuestionnaireStatus::CLOSED))) {
            throw new InvalidArgumentException('A CLOSED Questionnaire cannot be edited.');
        }

        $this->title = self::validateTitle($title);
        $this->description = self::normalizeNullableString($description);
        $this->responseEndAt = $responseEndAt;
        $this->touch($updatedBy);
    }

    public function publish(?PersonId $updatedBy): void
    {
        if (! $this->status->equals(QuestionnaireStatus::fromString(QuestionnaireStatus::DRAFT))) {
            throw new InvalidArgumentException('Only a DRAFT Questionnaire can be published.');
        }

        $this->status = QuestionnaireStatus::fromString(QuestionnaireStatus::PUBLISHED);
        $this->touch($updatedBy);
    }

    /**
     * §18: also reachable directly from the admin screen regardless of
     * `responseEndAt` ("管理画面上でCLOSEDにすることも可能").
     */
    public function close(?PersonId $updatedBy): void
    {
        if (! $this->status->equals(QuestionnaireStatus::fromString(QuestionnaireStatus::PUBLISHED))) {
            throw new InvalidArgumentException('Only a PUBLISHED Questionnaire can be closed.');
        }

        $this->status = QuestionnaireStatus::fromString(QuestionnaireStatus::CLOSED);
        $this->touch($updatedBy);
    }

    /**
     * §18/§43/§48: PUBLISHED and (no deadline, or the deadline has not
     * passed yet) - independent of the parent Production's own lifecycle
     * (§19/§46).
     */
    public function isAcceptingResponses(DateTimeImmutable $now): bool
    {
        if (! $this->status->equals(QuestionnaireStatus::fromString(QuestionnaireStatus::PUBLISHED))) {
            return false;
        }

        return $this->responseEndAt === null || $now <= $this->responseEndAt;
    }

    private static function validateTitle(string $title): string
    {
        $trimmed = trim($title);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Questionnaire title must not be empty.');
        }

        return $trimmed;
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function touch(?PersonId $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): QuestionnaireId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function status(): QuestionnaireStatus
    {
        return $this->status;
    }

    public function responseEndAt(): ?DateTimeImmutable
    {
        return $this->responseEndAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function createdBy(): ?PersonId
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?PersonId
    {
        return $this->updatedBy;
    }
}
