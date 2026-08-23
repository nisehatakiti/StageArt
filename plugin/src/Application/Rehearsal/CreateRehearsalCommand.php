<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

final class CreateRehearsalCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public ?string $title;
    public ?string $description;
    public ?string $startDateTime;
    public ?string $endDateTime;
    public ?string $timezone;
    public ?string $location;

    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        ?string $title,
        ?string $description,
        ?string $startDateTime,
        ?string $endDateTime,
        ?string $timezone,
        ?string $location
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->title = $title;
        $this->description = $description;
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
        $this->timezone = $timezone;
        $this->location = $location;
    }
}
