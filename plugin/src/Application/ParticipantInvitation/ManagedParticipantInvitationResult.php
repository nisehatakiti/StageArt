<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/**
 * R14-B §7.1 (stage 4 remainder, R14B-4R): one row of the managers'
 * `GET /productions/{id}/participant-invitations`. Every existing field of
 * ParticipantInvitationResult is kept unchanged (`status` stays the stored
 * status); the §7.1 fields are added next to them. The invitee's Person id
 * or name is never part of it.
 *
 * - effective_status: §5.1 (EXPIRED -> PRODUCTION_CLOSED -> INVITER_REVOKED
 *   -> PENDING for a stored PENDING; a stored CANCELLED(INVITER_REVOKED) as
 *   INVITER_REVOKED, §11.5 - decided 2026-11-18; any other CANCELLED as
 *   CANCELLED; ACCEPTED / REJECTED / CONSUMED as stored).
 * - responded_at, close_reason, closed_at: as stored.
 * - resend_count, resend_remaining: this invitation's resends and what is
 *   left of its MAX_RESENDS.
 * - send_status, last_sent_at: the attempt current_send_id points at
 *   (PENDING / SENT / FAILED / UNKNOWN, §6.2 / §11.3); both null for an
 *   invitation without one (made before R14-B) - decided 2026-11-18.
 * - send_limit: the address's 30-day limit (InvitationSendLimit); null when
 *   it cannot be counted because a key version used in the window is not
 *   configured - decided 2026-11-18.
 */
final class ManagedParticipantInvitationResult
{
    public ParticipantInvitationResult $invitation;
    public string $effectiveStatus;
    public ?string $respondedAt;
    public ?string $closeReason;
    public ?string $closedAt;
    public int $resendCount;
    public int $resendRemaining;
    public ?string $sendStatus;
    public ?string $lastSentAt;
    public ?InvitationSendLimit $sendLimit;

    public function __construct(
        ParticipantInvitationResult $invitation,
        string $effectiveStatus,
        ?string $respondedAt,
        ?string $closeReason,
        ?string $closedAt,
        int $resendCount,
        int $resendRemaining,
        ?string $sendStatus,
        ?string $lastSentAt,
        ?InvitationSendLimit $sendLimit
    ) {
        $this->invitation = $invitation;
        $this->effectiveStatus = $effectiveStatus;
        $this->respondedAt = $respondedAt;
        $this->closeReason = $closeReason;
        $this->closedAt = $closedAt;
        $this->resendCount = $resendCount;
        $this->resendRemaining = $resendRemaining;
        $this->sendStatus = $sendStatus;
        $this->lastSentAt = $lastSentAt;
        $this->sendLimit = $sendLimit;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->invitation->toArray() + [
            'effective_status' => $this->effectiveStatus,
            'responded_at' => $this->respondedAt,
            'close_reason' => $this->closeReason,
            'closed_at' => $this->closedAt,
            'resend_count' => $this->resendCount,
            'resend_remaining' => $this->resendRemaining,
            'send_status' => $this->sendStatus,
            'last_sent_at' => $this->lastSentAt,
            'send_limit' => $this->sendLimit !== null ? $this->sendLimit->toArray() : null,
        ];
    }
}
