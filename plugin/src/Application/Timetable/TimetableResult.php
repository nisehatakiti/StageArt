<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use StageArt\Domain\Timetable\Timetable;

final class TimetableResult
{
    public string $id;
    public string $rehearsalId;
    public int $version;
    public string $status;
    public ?string $changeSummary;
    public string $createdBy;
    public string $createdAt;
    public string $updatedBy;
    public string $updatedAt;
    public ?string $publishedBy;
    public ?string $publishedAt;

    private function __construct(
        string $id,
        string $rehearsalId,
        int $version,
        string $status,
        ?string $changeSummary,
        string $createdBy,
        string $createdAt,
        string $updatedBy,
        string $updatedAt,
        ?string $publishedBy,
        ?string $publishedAt
    ) {
        $this->id = $id;
        $this->rehearsalId = $rehearsalId;
        $this->version = $version;
        $this->status = $status;
        $this->changeSummary = $changeSummary;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $updatedAt;
        $this->publishedBy = $publishedBy;
        $this->publishedAt = $publishedAt;
    }

    public static function fromDomain(Timetable $timetable): self
    {
        return new self(
            $timetable->id()->toString(),
            $timetable->rehearsalId()->toString(),
            $timetable->version(),
            $timetable->status()->toString(),
            $timetable->changeSummary(),
            $timetable->createdBy()->toString(),
            $timetable->createdAt()->format(DATE_ATOM),
            $timetable->updatedBy()->toString(),
            $timetable->updatedAt()->format(DATE_ATOM),
            $timetable->publishedBy() !== null ? $timetable->publishedBy()->toString() : null,
            $timetable->publishedAt() !== null ? $timetable->publishedAt()->format(DATE_ATOM) : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rehearsal_id' => $this->rehearsalId,
            'version' => $this->version,
            'status' => $this->status,
            'change_summary' => $this->changeSummary,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_by' => $this->updatedBy,
            'updated_at' => $this->updatedAt,
            'published_by' => $this->publishedBy,
            'published_at' => $this->publishedAt,
        ];
    }
}
