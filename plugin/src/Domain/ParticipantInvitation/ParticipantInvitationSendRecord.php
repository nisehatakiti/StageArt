<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use DateTimeImmutable;

/**
 * R14-B §6.2 / §7.1 (stage 4 remainder, R14B-4R): one stored send attempt
 * as the managers' invitation list reads it - its key pair (to count the
 * sends to one address, §6.7), its stored status and when it was accepted.
 * A read model only; the address itself is never part of it.
 */
final class ParticipantInvitationSendRecord
{
    private string $id;
    private string $emailKeyVersion;
    private string $emailKey;
    private string $status;
    private DateTimeImmutable $requestedAt;

    public function __construct(string $id, string $emailKeyVersion, string $emailKey, string $status, DateTimeImmutable $requestedAt)
    {
        $this->id = $id;
        $this->emailKeyVersion = $emailKeyVersion;
        $this->emailKey = $emailKey;
        $this->status = $status;
        $this->requestedAt = $requestedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function emailKeyVersion(): string
    {
        return $this->emailKeyVersion;
    }

    public function emailKey(): string
    {
        return $this->emailKey;
    }

    /** The stored status: PENDING / SENT / FAILED / SUPERSEDED. */
    public function status(): string
    {
        return $this->status;
    }

    public function requestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    /**
     * §6.2 / §11.3: the status shown for this attempt - UNKNOWN (derived,
     * never stored) when it is still PENDING UNKNOWN_AFTER after it was
     * accepted (the result was never recorded), otherwise the stored one.
     */
    public function displayStatusAt(DateTimeImmutable $now): string
    {
        if ($this->status === ParticipantInvitationSend::PENDING
            && $this->requestedAt->add(new \DateInterval(ParticipantInvitationSend::UNKNOWN_AFTER)) <= $now
        ) {
            return ParticipantInvitationSend::UNKNOWN;
        }

        return $this->status;
    }
}
