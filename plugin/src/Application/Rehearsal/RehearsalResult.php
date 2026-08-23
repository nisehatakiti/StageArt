<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Domain\Rehearsal\Rehearsal;

final class RehearsalResult
{
    public string $id;
    public string $productionId;
    public ?string $title;
    public ?string $description;
    public ?string $startDateTime;
    public ?string $endDateTime;
    public ?string $timezone;
    public ?string $location;
    public string $status;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $productionId,
        ?string $title,
        ?string $description,
        ?string $startDateTime,
        ?string $endDateTime,
        ?string $timezone,
        ?string $location,
        string $status,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->title = $title;
        $this->description = $description;
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
        $this->timezone = $timezone;
        $this->location = $location;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(Rehearsal $rehearsal): self
    {
        return new self(
            $rehearsal->id()->toString(),
            $rehearsal->productionId()->toString(),
            $rehearsal->title(),
            $rehearsal->description(),
            $rehearsal->startDateTime() !== null ? $rehearsal->startDateTime()->format(DATE_ATOM) : null,
            $rehearsal->endDateTime() !== null ? $rehearsal->endDateTime()->format(DATE_ATOM) : null,
            $rehearsal->timezone(),
            $rehearsal->location(),
            $rehearsal->status()->toString(),
            $rehearsal->createdAt()->format(DATE_ATOM),
            $rehearsal->updatedAt()->format(DATE_ATOM)
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
            'start_date_time' => $this->startDateTime,
            'end_date_time' => $this->endDateTime,
            'timezone' => $this->timezone,
            'location' => $this->location,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
