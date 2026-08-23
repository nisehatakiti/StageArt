<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use StageArt\Domain\TimetableItem\TimetableItem;

final class TimetableItemResult
{
    public string $id;
    public string $timetableId;
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
    public string $createdAt;
    public string $updatedAt;

    /**
     * @param string[] $targetPersonIds
     */
    private function __construct(
        string $id,
        string $timetableId,
        string $title,
        ?string $description,
        string $startDateTime,
        ?string $endDateTime,
        ?int $displayOrder,
        ?string $category,
        ?string $venue,
        ?string $participantType,
        array $targetPersonIds,
        ?string $notes,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->timetableId = $timetableId;
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
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(TimetableItem $item): self
    {
        return new self(
            $item->id()->toString(),
            $item->timetableId()->toString(),
            $item->title(),
            $item->description(),
            $item->startDateTime()->format(DATE_ATOM),
            $item->endDateTime() !== null ? $item->endDateTime()->format(DATE_ATOM) : null,
            $item->displayOrder(),
            $item->category(),
            $item->venue(),
            $item->participantType() !== null ? $item->participantType()->toString() : null,
            array_map(static fn ($personId): string => $personId->toString(), $item->targetPersonIds()),
            $item->notes(),
            $item->createdAt()->format(DATE_ATOM),
            $item->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timetable_id' => $this->timetableId,
            'title' => $this->title,
            'description' => $this->description,
            'start_date_time' => $this->startDateTime,
            'end_date_time' => $this->endDateTime,
            'display_order' => $this->displayOrder,
            'category' => $this->category,
            'venue' => $this->venue,
            'participant_type' => $this->participantType,
            'target_person_ids' => $this->targetPersonIds,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
