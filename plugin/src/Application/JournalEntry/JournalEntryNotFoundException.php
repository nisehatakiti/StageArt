<?php

declare(strict_types=1);

namespace StageArt\Application\JournalEntry;

use RuntimeException;

final class JournalEntryNotFoundException extends RuntimeException
{
    public function __construct(string $journalEntryId)
    {
        parent::__construct("JournalEntry not found: {$journalEntryId}");
    }
}
