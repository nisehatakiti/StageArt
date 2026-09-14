<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

final class UpdateRehearsalCommand
{
    public string $rehearsalId;
    public int $requestedByWordPressUserId;
    public ?string $title;
    public ?string $description;
    public ?string $startDateTime;
    public ?string $endDateTime;
    public ?string $timezone;
    public ?string $location;
    /** Phase 7 (Rehearsal仕様整合): whole-field overwrite, matching every
     * other field here - the caller must pass the Rehearsal's current
     * value back unchanged to leave it as-is, null to clear it. */
    public ?string $responseDeadline;

    public function __construct(
        string $rehearsalId,
        int $requestedByWordPressUserId,
        ?string $title,
        ?string $description,
        ?string $startDateTime,
        ?string $endDateTime,
        ?string $timezone,
        ?string $location,
        ?string $responseDeadline = null
    ) {
        $this->rehearsalId = $rehearsalId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->title = $title;
        $this->description = $description;
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
        $this->timezone = $timezone;
        $this->location = $location;
        $this->responseDeadline = $responseDeadline;
    }
}
