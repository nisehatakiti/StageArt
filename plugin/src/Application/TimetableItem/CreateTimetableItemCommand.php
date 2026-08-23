<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

final class CreateTimetableItemCommand
{
    public string $rehearsalId;
    public int $requestedByWordPressUserId;
    public string $title;
    public ?string $description;
    public string $startDateTime;
    public ?string $endDateTime;
    public ?int $displayOrder;
    public ?string $category;
    public ?string $venue;
    public ?string $participantType;
    /** @var string[] */
    public array $targetPersonIds;
    public ?string $notes;

    /**
     * @param string[] $targetPersonIds
     */
    public function __construct(
        string $rehearsalId,
        int $requestedByWordPressUserId,
        string $title,
        ?string $description,
        string $startDateTime,
        ?string $endDateTime,
        ?int $displayOrder,
        ?string $category,
        ?string $venue,
        ?string $participantType,
        array $targetPersonIds,
        ?string $notes
    ) {
        $this->rehearsalId = $rehearsalId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->title = $title;
        $this->description = $description;
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
        $this->displayOrder = $displayOrder;
        $this->category = $category;
        $this->venue = $venue;
        $this->participantType = $participantType;
        $this->targetPersonIds = $targetPersonIds;
        $this->notes = $notes;
    }
}
