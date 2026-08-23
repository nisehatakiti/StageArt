<?php

declare(strict_types=1);

namespace StageArt\Domain\TimetableItem;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Timetable\TimetableId;

/**
 * Field selection follows Timetable.md literally: Title ("を設定する")
 * and Start Time ("を持つ") are phrased as definite/required, so they
 * are non-nullable here; Description, End Time, Order, Category, Venue,
 * Participant Type, target Persons, and Notes are all phrased as
 * optional ("できる"), so they stay nullable/empty-by-default - matching
 * Rehearsal.php's precedent of keeping optional Blueprint fields as
 * plain nullable scalars rather than Value Objects.
 *
 * Category is a plain nullable string, not an enum VO: Timetable.md's
 * "Category" section lists REHEARSAL/BREAK/MEETING/SETUP/TECHNICAL/
 * OTHER as "例えば...など" (examples, not an exhaustive closed set),
 * unlike RehearsalStatus/TimetableStatus, which Blueprint defines as
 * closed enumerations. Fixing Category to a VO here would be inventing
 * a closed list the Blueprint does not actually commit to.
 *
 * Role association reuses the existing Participant Domain's
 * ParticipantType VO (currently CAST/STAFF only - see
 * ParticipantType.php's own docblock). Person association reuses
 * PersonId directly, resolved against Production Participants at the
 * Application layer (see CreateTimetableItemUseCase) - this Entity does
 * not duplicate Participant data, only references PersonId values, per
 * Timetable.md's "Participant and Timetable" section.
 *
 * No time-overlap validation is enforced: Phase 3 instruction §10
 * explicitly states simultaneous/overlapping Timetable Items across
 * different Roles are a normal, expected state (e.g. sound check and
 * lighting adjustment happening concurrently), not an error condition.
 */
final class TimetableItem
{
    private TimetableItemId $id;
    private TimetableId $timetableId;
    private string $title;
    private ?string $description;
    private DateTimeImmutable $startDateTime;
    private ?DateTimeImmutable $endDateTime;
    private ?int $displayOrder;
    private ?string $category;
    private ?string $venue;
    private ?ParticipantType $participantType;
    /** @var PersonId[] */
    private array $targetPersonIds;
    private ?string $notes;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    /**
     * @param PersonId[] $targetPersonIds
     */
    private function __construct(
        TimetableItemId $id,
        TimetableId $timetableId,
        string $title,
        ?string $description,
        DateTimeImmutable $startDateTime,
        ?DateTimeImmutable $endDateTime,
        ?int $displayOrder,
        ?string $category,
        ?string $venue,
        ?ParticipantType $participantType,
        array $targetPersonIds,
        ?string $notes,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
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

    /**
     * @param PersonId[] $targetPersonIds
     */
    public static function create(
        TimetableId $timetableId,
        string $title,
        ?string $description,
        DateTimeImmutable $startDateTime,
        ?DateTimeImmutable $endDateTime,
        ?int $displayOrder,
        ?string $category,
        ?string $venue,
        ?ParticipantType $participantType,
        array $targetPersonIds,
        ?string $notes
    ): self {
        self::assertTitle($title);

        $now = new DateTimeImmutable();

        return new self(
            TimetableItemId::generate(),
            $timetableId,
            trim($title),
            $description,
            $startDateTime,
            $endDateTime,
            $displayOrder,
            $category,
            $venue,
            $participantType,
            array_values($targetPersonIds),
            $notes,
            $now,
            $now
        );
    }

    /**
     * @param PersonId[] $targetPersonIds
     */
    public static function reconstitute(
        TimetableItemId $id,
        TimetableId $timetableId,
        string $title,
        ?string $description,
        DateTimeImmutable $startDateTime,
        ?DateTimeImmutable $endDateTime,
        ?int $displayOrder,
        ?string $category,
        ?string $venue,
        ?ParticipantType $participantType,
        array $targetPersonIds,
        ?string $notes,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $timetableId,
            $title,
            $description,
            $startDateTime,
            $endDateTime,
            $displayOrder,
            $category,
            $venue,
            $participantType,
            array_values($targetPersonIds),
            $notes,
            $createdAt,
            $updatedAt
        );
    }

    /**
     * @param PersonId[] $targetPersonIds
     */
    public function update(
        string $title,
        ?string $description,
        DateTimeImmutable $startDateTime,
        ?DateTimeImmutable $endDateTime,
        ?int $displayOrder,
        ?string $category,
        ?string $venue,
        ?ParticipantType $participantType,
        array $targetPersonIds,
        ?string $notes
    ): void {
        self::assertTitle($title);

        $this->title = trim($title);
        $this->description = $description;
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
        $this->displayOrder = $displayOrder;
        $this->category = $category;
        $this->venue = $venue;
        $this->participantType = $participantType;
        $this->targetPersonIds = array_values($targetPersonIds);
        $this->notes = $notes;
        $this->touch();
    }

    private static function assertTitle(string $title): void
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('TimetableItem title must not be empty.');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): TimetableItemId
    {
        return $this->id;
    }

    public function timetableId(): TimetableId
    {
        return $this->timetableId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function startDateTime(): DateTimeImmutable
    {
        return $this->startDateTime;
    }

    public function endDateTime(): ?DateTimeImmutable
    {
        return $this->endDateTime;
    }

    public function displayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function venue(): ?string
    {
        return $this->venue;
    }

    public function participantType(): ?ParticipantType
    {
        return $this->participantType;
    }

    /**
     * @return PersonId[]
     */
    public function targetPersonIds(): array
    {
        return $this->targetPersonIds;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
