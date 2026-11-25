<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;

/**
 * R14-B §5.3 / §6.2: the 30-day send limit of one Production + normalised
 * address, from the request times of its sends in the window. The one
 * rule both the create / resend check (InvitationSendService::
 * assertWithinLimit(), 429 `available_at`) and the managers' list
 * (`send_limit`, §7.1) use.
 *
 * - sent: sends accepted in the window (every status counts, §6.2).
 * - remaining: MAX_SENDS_PER_ADDRESS - sent, never below 0.
 * - availableAt: when one more send fits - the send that has to leave the
 *   window, plus 30 days. Null while a send fits now.
 */
final class InvitationSendLimit
{
    public int $sentIn30Days;
    public int $remainingIn30Days;
    public ?DateTimeImmutable $availableAt;

    private function __construct(int $sentIn30Days, int $remainingIn30Days, ?DateTimeImmutable $availableAt)
    {
        $this->sentIn30Days = $sentIn30Days;
        $this->remainingIn30Days = $remainingIn30Days;
        $this->availableAt = $availableAt;
    }

    /**
     * @param DateTimeImmutable[] $requestedAts the sends in the window, oldest first
     */
    public static function fromRequestedAts(array $requestedAts): self
    {
        $requestedAts = array_values($requestedAts);
        $count = count($requestedAts);
        $max = InvitationSendService::MAX_SENDS_PER_ADDRESS;

        if ($count < $max) {
            return new self($count, $max - $count, null);
        }

        return new self($count, 0, $requestedAts[$count - $max]->add(new DateInterval(InvitationSendService::WINDOW)));
    }

    public function allowsAnotherSend(): bool
    {
        return $this->remainingIn30Days > 0;
    }

    /**
     * @return array{sent_in_30_days: int, remaining_in_30_days: int, available_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'sent_in_30_days' => $this->sentIn30Days,
            'remaining_in_30_days' => $this->remainingIn30Days,
            'available_at' => $this->availableAt !== null ? $this->availableAt->format(DATE_ATOM) : null,
        ];
    }
}
