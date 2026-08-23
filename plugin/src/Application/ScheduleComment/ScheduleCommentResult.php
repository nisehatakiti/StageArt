<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use StageArt\Domain\ScheduleComment\ScheduleComment;

final class ScheduleCommentResult
{
    public string $id;
    public ?string $rehearsalId;
    public ?string $timetableItemId;
    public string $authorPersonId;
    public string $body;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        ?string $rehearsalId,
        ?string $timetableItemId,
        string $authorPersonId,
        string $body,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->rehearsalId = $rehearsalId;
        $this->timetableItemId = $timetableItemId;
        $this->authorPersonId = $authorPersonId;
        $this->body = $body;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(ScheduleComment $comment): self
    {
        return new self(
            $comment->id()->toString(),
            $comment->rehearsalId() !== null ? $comment->rehearsalId()->toString() : null,
            $comment->timetableItemId() !== null ? $comment->timetableItemId()->toString() : null,
            $comment->authorPersonId()->toString(),
            $comment->body()->toString(),
            $comment->createdAt()->format(DATE_ATOM),
            $comment->updatedAt()->format(DATE_ATOM)
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
            'timetable_item_id' => $this->timetableItemId,
            'author_person_id' => $this->authorPersonId,
            'body' => $this->body,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
