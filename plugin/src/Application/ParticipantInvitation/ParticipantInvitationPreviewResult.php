<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/**
 * §19: the unauthenticated, token-based preview shown on the
 * registration screen before the invitee has an account - deliberately
 * minimal (production name, invited name, email, participantType,
 * status) and never includes the token itself, the invitation id, or
 * invitedByPersonId. `name` (§3) lets the registration screen pre-fill
 * the invitee's name field - purely a display default, never written to
 * any Person automatically (see ResolveParticipantInvitationUseCase,
 * which never reads this field at all - the real Person's name comes
 * from the invitee's own registration input, not from this preview).
 */
final class ParticipantInvitationPreviewResult
{
    public string $productionName;
    public string $email;
    public string $name;
    public string $participantType;
    public string $status;

    public function __construct(string $productionName, string $email, string $name, string $participantType, string $status)
    {
        $this->productionName = $productionName;
        $this->email = $email;
        $this->name = $name;
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
            'name' => $this->name,
            'participant_type' => $this->participantType,
            'status' => $this->status,
        ];
    }
}
