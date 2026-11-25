<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantEvent;

/**
 * R14-B §6.3: append-only. The application only ever adds events - there
 * is no update or delete (the 2-year retention purge is a separate,
 * later scheduled job, §6.6).
 */
interface ParticipantEventRepositoryInterface
{
    /**
     * Inserts the event inside the caller's transaction (no lock taken).
     * Throws when the write fails, so the business change is rolled back
     * together with it.
     */
    public function append(ParticipantEvent $event): void;
}
