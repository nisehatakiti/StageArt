<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/**
 * R14-B §7.2 (D7, N5): one item of `GET /me/participant-invitations`, for
 * the invitee only. Deliberately has no field for the invitation's e-mail
 * address, the name or remarks the manager typed, the token / token hash,
 * or any other Person's data - only what the invitee needs to decide.
 */
final class MyParticipantInvitationResult
{
    public string $id;
    public string $productionId;
    public string $productionName;
    public ?string $organizationName;
    public string $participantType;
    public string $invitedByDisplayName;
    public string $expiresAt;
    public string $effectiveStatus;
    public bool $alreadyParticipant;
    public bool $canAccept;
    public bool $canReject;
    public ?string $unavailableReason;

    public function __construct(
        string $id,
        string $productionId,
        string $productionName,
        ?string $organizationName,
        string $participantType,
        string $invitedByDisplayName,
        string $expiresAt,
        string $effectiveStatus,
        bool $alreadyParticipant,
        bool $canAccept,
        bool $canReject,
        ?string $unavailableReason
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->productionName = $productionName;
        $this->organizationName = $organizationName;
        $this->participantType = $participantType;
        $this->invitedByDisplayName = $invitedByDisplayName;
        $this->expiresAt = $expiresAt;
        $this->effectiveStatus = $effectiveStatus;
        $this->alreadyParticipant = $alreadyParticipant;
        $this->canAccept = $canAccept;
        $this->canReject = $canReject;
        $this->unavailableReason = $unavailableReason;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'production_id' => $this->productionId,
            'production_name' => $this->productionName,
            'organization_name' => $this->organizationName,
            'participant_type' => $this->participantType,
            'invited_by_display_name' => $this->invitedByDisplayName,
            'expires_at' => $this->expiresAt,
            'effective_status' => $this->effectiveStatus,
            'already_participant' => $this->alreadyParticipant,
            'can_accept' => $this->canAccept,
            'can_reject' => $this->canReject,
            'unavailable_reason' => $this->unavailableReason,
        ];
    }
}
