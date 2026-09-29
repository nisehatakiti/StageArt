<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Application\Participant\ParticipantResult;

/**
 * §16/§17: this Use Case's single entry point can end in one of three
 * ways depending on whether the email already belongs to an existing
 * Person, and (if not) whether a still-usable invitation already exists
 * - `outcome` disambiguates which for the REST response, so the caller
 * never has to guess from which of `participant`/`invitation` happens to
 * be non-null.
 */
final class CreateParticipantInvitationResult
{
    public const OUTCOME_PARTICIPANT_ADDED = 'PARTICIPANT_ADDED';
    public const OUTCOME_INVITATION_CREATED = 'INVITATION_CREATED';
    public const OUTCOME_INVITATION_RESENT = 'INVITATION_RESENT';

    public string $outcome;
    public ?ParticipantResult $participant;
    public ?ParticipantInvitationResult $invitation;

    private function __construct(string $outcome, ?ParticipantResult $participant, ?ParticipantInvitationResult $invitation)
    {
        $this->outcome = $outcome;
        $this->participant = $participant;
        $this->invitation = $invitation;
    }

    public static function forExistingPerson(ParticipantResult $participant): self
    {
        return new self(self::OUTCOME_PARTICIPANT_ADDED, $participant, null);
    }

    public static function forNewInvitation(ParticipantInvitationResult $invitation): self
    {
        return new self(self::OUTCOME_INVITATION_CREATED, null, $invitation);
    }

    public static function forResentInvitation(ParticipantInvitationResult $invitation): self
    {
        return new self(self::OUTCOME_INVITATION_RESENT, null, $invitation);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'participant' => $this->participant?->toArray(),
            'invitation' => $this->invitation?->toArray(),
        ];
    }
}
