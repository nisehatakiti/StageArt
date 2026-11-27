<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use InvalidArgumentException;

/**
 * The create API ends in one of three ways:
 * - INVITATION_SENT (a new invitation) or INVITATION_RESENT (a PENDING one
 *   to the same address and type was resent) - `invitation` is what the
 *   admin entered (address, name, type, remarks);
 * - MEMBER_ADDED (invitation policy 2026-11-27, like an organization
 *   invitation to a verified account): the address belongs to a verified
 *   account, which was registered as a Participant at once - `participant_id`
 *   is the row, and no invitation is sent.
 */
final class CreateParticipantInvitationResult
{
    public const OUTCOME_INVITATION_SENT = 'INVITATION_SENT';
    public const OUTCOME_INVITATION_RESENT = 'INVITATION_RESENT';
    public const OUTCOME_MEMBER_ADDED = 'MEMBER_ADDED';

    public string $outcome;
    public ?ParticipantInvitationResult $invitation;
    public ?string $participantId;

    private function __construct(string $outcome, ?ParticipantInvitationResult $invitation, ?string $participantId = null)
    {
        $this->outcome = $outcome;
        $this->invitation = $invitation;
        $this->participantId = $participantId;
    }

    public static function memberAdded(string $participantId): self
    {
        return new self(self::OUTCOME_MEMBER_ADDED, null, $participantId);
    }

    public static function of(string $outcome, ParticipantInvitationResult $invitation): self
    {
        if (! in_array($outcome, [self::OUTCOME_INVITATION_SENT, self::OUTCOME_INVITATION_RESENT], true)) {
            throw new InvalidArgumentException("Invalid outcome: {$outcome}");
        }

        return new self($outcome, $invitation);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->invitation === null) {
            return [
                'outcome' => $this->outcome,
                'participant_id' => $this->participantId,
            ];
        }

        return [
            'outcome' => $this->outcome,
            'invitation' => $this->invitation->toArray(),
        ];
    }
}
