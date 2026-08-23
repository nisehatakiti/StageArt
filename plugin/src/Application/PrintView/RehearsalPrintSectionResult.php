<?php

declare(strict_types=1);

namespace StageArt\Application\PrintView;

use StageArt\Application\TimetableItem\TimetableItemResult;

/**
 * One Rehearsal's contribution to a Production-wide Print View. Phase
 * 4.5 instruction §18 requires Version/Published-at to be identifiable
 * per printed sheet - since a Production spans multiple independently
 * versioned Rehearsals (Timetable.md's "Timetable Versioning"), that
 * identification is necessarily per-Rehearsal-section, not a single
 * Production-wide version number.
 */
final class RehearsalPrintSectionResult
{
    public string $rehearsalId;
    public ?string $rehearsalTitle;
    public ?string $rehearsalStartDateTime;
    public ?int $timetableVersion;
    public ?string $publishedAt;
    public ?string $changeSummary;
    /** @var TimetableItemResult[] */
    public array $items;

    /**
     * @param TimetableItemResult[] $items
     */
    public function __construct(
        string $rehearsalId,
        ?string $rehearsalTitle,
        ?string $rehearsalStartDateTime,
        ?int $timetableVersion,
        ?string $publishedAt,
        ?string $changeSummary,
        array $items
    ) {
        $this->rehearsalId = $rehearsalId;
        $this->rehearsalTitle = $rehearsalTitle;
        $this->rehearsalStartDateTime = $rehearsalStartDateTime;
        $this->timetableVersion = $timetableVersion;
        $this->publishedAt = $publishedAt;
        $this->changeSummary = $changeSummary;
        $this->items = $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rehearsal_id' => $this->rehearsalId,
            'rehearsal_title' => $this->rehearsalTitle,
            'rehearsal_start_date_time' => $this->rehearsalStartDateTime,
            'timetable_version' => $this->timetableVersion,
            'published_at' => $this->publishedAt,
            'change_summary' => $this->changeSummary,
            'items' => array_map(static fn (TimetableItemResult $item): array => $item->toArray(), $this->items),
        ];
    }
}
