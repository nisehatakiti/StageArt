<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use InvalidArgumentException;

/**
 * PR #7 R14-B stage 4 (design doc §7.1 / §9.2): the create API ends in one
 * of two ways only - INVITATION_SENT (a new invitation) or
 * INVITATION_RESENT (a PENDING one to the same address and type was
 * resent). There is no `participant` (no one is added directly any more)
 * and nothing tells whether the address belongs to an account; the
 * `invitation` is what the admin entered (address, name, type, remarks).
 */
final class CreateParticipantInvitationResult
{
    public const OUTCOME_INVITATION_SENT = 'INVITATION_SENT';
    public const OUTCOME_INVITATION_RESENT = 'INVITATION_RESENT';

    public string $outcome;
    public ParticipantInvitationResult $invitation;

    private function __construct(string $outcome, ParticipantInvitationResult $invitation)
    {
        $this->outcome = $outcome;
        $this->invitation = $invitation;
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
        return [
            'outcome' => $this->outcome,
            'invitation' => $this->invitation->toArray(),
        ];
    }
}
