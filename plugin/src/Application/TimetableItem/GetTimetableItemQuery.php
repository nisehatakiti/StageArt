<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

final class GetTimetableItemQuery
{
    public string $timetableItemId;
    public int $requestedByWordPressUserId;

    public function __construct(string $timetableItemId, int $requestedByWordPressUserId)
    {
        $this->timetableItemId = $timetableItemId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
