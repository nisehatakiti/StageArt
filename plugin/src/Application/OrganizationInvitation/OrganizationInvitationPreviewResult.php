<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

/** Unauthenticated registration-screen preview - mirrors
 * ParticipantInvitationPreviewResult's own shape. */
final class OrganizationInvitationPreviewResult
{
    public string $organizationName;
    public string $email;
    public string $invitedName;
    public string $status;

    public function __construct(string $organizationName, string $email, string $invitedName, string $status)
    {
        $this->organizationName = $organizationName;
        $this->email = $email;
        $this->invitedName = $invitedName;
        $this->status = $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'organization_name' => $this->organizationName,
            'email' => $this->email,
            'name' => $this->invitedName,
            'status' => $this->status,
        ];
    }
}
