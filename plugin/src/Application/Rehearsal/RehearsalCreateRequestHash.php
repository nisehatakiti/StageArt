<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalActivityType;

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §26.3): the content hash
 * that decides whether a request sent again with the same idempotency key
 * is "the same request" (answer with the Rehearsal it created) or a
 * different one (409).
 *
 * SHA-256 of a JSON object of the request after it has been read, so two
 * requests that mean the same thing hash the same:
 * - `v`: "rehearsal-create/1" (format version of this list)
 * - `production_id`: lower case
 * - `title`, `location`: trimmed; empty -> null
 * - `description`: CRLF -> LF; empty or only whitespace -> null
 * - `start_date_time`, `end_date_time`, `response_deadline`: the parsed
 *   instant in the Rehearsal's timezone as ISO 8601 (so "10:00+09:00" and
 *   "01:00Z" are equal); no deadline -> null
 * - `timezone`: the resolved IANA name (none given -> Asia/Tokyo)
 * - `person_ids`: lower case, duplicates removed, sorted
 * - `activity_type`: the resolved type (none given -> REHEARSAL)
 * - `confirm_on_create`: boolean
 *
 * Not part of the hash: the idempotency key itself and who sent it (the
 * sender is compared separately).
 */
final class RehearsalCreateRequestHash
{
    public const FORMAT = 'rehearsal-create/1';

    /**
     * @param string[] $targetPersonIds
     * @return array<string, mixed> the normalized request (what is hashed)
     */
    public static function normalize(
        ProductionId $productionId,
        ?string $title,
        ?string $description,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?DateTimeImmutable $responseDeadline,
        string $timezone,
        ?string $location,
        array $targetPersonIds,
        ?RehearsalActivityType $activityType,
        bool $confirmOnCreate
    ): array {
        $personIds = array_values(array_unique(array_map(
            static fn (string $id): string => strtolower(trim($id)),
            $targetPersonIds
        )));
        sort($personIds, SORT_STRING);

        return [
            'v' => self::FORMAT,
            'production_id' => strtolower($productionId->toString()),
            'title' => self::trimmedOrNull($title),
            'description' => self::descriptionOrNull($description),
            'start_date_time' => $start->format(DATE_ATOM),
            'end_date_time' => $end->format(DATE_ATOM),
            'response_deadline' => $responseDeadline !== null ? $responseDeadline->format(DATE_ATOM) : null,
            'timezone' => $timezone,
            'location' => self::trimmedOrNull($location),
            'person_ids' => $personIds,
            'activity_type' => $activityType !== null ? $activityType->toString() : RehearsalActivityType::REHEARSAL,
            'confirm_on_create' => $confirmOnCreate,
        ];
    }

    /**
     * @param array<string, mixed> $normalized from normalize()
     */
    public static function hash(array $normalized): string
    {
        return hash('sha256', (string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function trimmedOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function descriptionOrNull(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return str_replace("\r\n", "\n", $value);
    }
}
