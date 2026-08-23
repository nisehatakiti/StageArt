<?php

declare(strict_types=1);

namespace StageArt\Application\JournalEntry;

final class PostJournalEntryCommand
{
    public string $journalEntryId;
    public int $requestedByWordPressUserId;

    public function __construct(string $journalEntryId, int $requestedByWordPressUserId)
    {
        $this->journalEntryId = $journalEntryId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
