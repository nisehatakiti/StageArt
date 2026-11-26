<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use StageArt\Application\Organization\OrganizationMemberResult;

/** Mirrors CreateParticipantInvitationResult's own 3-outcome shape. */
final class CreateOrganizationInvitationResult
{
    public const OUTCOME_MEMBER_ADDED = 'MEMBER_ADDED';
    public const OUTCOME_INVITATION_CREATED = 'INVITATION_CREATED';
    public const OUTCOME_INVITATION_RESENT = 'INVITATION_RESENT';

    public string $outcome;
    public ?OrganizationMemberResult $member;
    public ?OrganizationInvitationResult $invitation;

    private function __construct(string $outcome, ?OrganizationMemberResult $member, ?OrganizationInvitationResult $invitation)
    {
        $this->outcome = $outcome;
        $this->member = $member;
        $this->invitation = $invitation;
    }

    public static function forExistingPerson(OrganizationMemberResult $member): self
    {
        return new self(self::OUTCOME_MEMBER_ADDED, $member, null);
    }

    public static function forNewInvitation(OrganizationInvitationResult $invitation): self
    {
        return new self(self::OUTCOME_INVITATION_CREATED, null, $invitation);
    }

    public static function forResentInvitation(OrganizationInvitationResult $invitation): self
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
            'member' => $this->member?->toArray(),
            'invitation' => $this->invitation?->toArray(),
        ];
    }
}
