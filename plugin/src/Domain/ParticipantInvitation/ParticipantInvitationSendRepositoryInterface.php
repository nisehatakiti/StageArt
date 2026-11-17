<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use DateTimeImmutable;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B §6.2 / §6.7 / §11.3 (stage 4). Every read throws on failure (never
 * reads as "no sends" - that would let a send through the 30-day limit);
 * every write is checked.
 */
interface ParticipantInvitationSendRepositoryInterface
{
    /** INSERT of a PENDING attempt (inside the create / resend transaction). */
    public function add(ParticipantInvitationSend $send): void;

    /**
     * §6.7 step 1: the key versions used by this Production's sends
     * requested at or after `$since`.
     *
     * @return string[]
     */
    public function keyVersionsSince(ProductionId $productionId, DateTimeImmutable $since): array;

    /**
     * §6.7 step 3: requested_at of this Production's sends at or after
     * `$since` whose (email_key_version, email_key) pair is one of
     * `$keysByVersion`, oldest first.
     *
     * @param array<string, string> $keysByVersion version => email_key
     * @return DateTimeImmutable[]
     */
    public function requestedAtsForKeysSince(ProductionId $productionId, DateTimeImmutable $since, array $keysByVersion): array;

    /**
     * §11.3 step 5: records the result of this attempt only while it is
     * still PENDING (UPDATE ... WHERE id = ? AND status = 'PENDING'), so a
     * result is never recorded twice and an older attempt never overwrites
     * anything else. True when this call recorded it.
     */
    public function completeIfPending(string $sendId, string $status, DateTimeImmutable $completedAt): bool;
}
