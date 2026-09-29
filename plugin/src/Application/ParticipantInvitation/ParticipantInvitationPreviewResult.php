<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/**
 * §19: the unauthenticated, token-based preview shown on the
 * registration screen before the invitee has an account - deliberately
 * minimal (production name, participantType, status) and never includes
 * the token itself, the invitation id, or invitedByPersonId.
 */
final class ParticipantInvitationPreviewResult
{
    public string $productionName;
    public string $email;
    public string $participantType;
    public string $status;

    public function __construct(string $productionName, string $email, string $participantType, string $status)
    {
        $this->productionName = $productionName;
        $this->email = $email;
        $this->participantType = $participantType;
        $this->status = $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'production_name' => $this->productionName,
            'email' => $this->email,
            'participant_type' => $this->participantType,
            'status' => $this->status,
        ];
    }
}
