<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use StageArt\Domain\OrganizationInvitation\OrganizationInvitation;

/** Never carries the raw token or its hash - mirrors
 * ParticipantInvitationResult's own rule. */
final class OrganizationInvitationResult
{
    public string $id;
    public string $organizationId;
    public string $email;
    public string $invitedName;
    public string $invitedByPersonId;
    public ?string $remarks;
    public string $status;
    public string $createdAt;
    public string $expiresAt;
    public ?string $consumedAt;

    public function __construct(
        string $id,
        string $organizationId,
        string $email,
        string $invitedName,
        string $invitedByPersonId,
        ?string $remarks,
        string $status,
        string $createdAt,
        string $expiresAt,
        ?string $consumedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->email = $email;
        $this->invitedName = $invitedName;
        $this->invitedByPersonId = $invitedByPersonId;
        $this->remarks = $remarks;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->consumedAt = $consumedAt;
    }

    public static function fromDomain(OrganizationInvitation $invitation): self
    {
        return new self(
            $invitation->id()->toString(),
            $invitation->organizationId()->toString(),
            $invitation->email(),
            $invitation->invitedName(),
            $invitation->invitedByPersonId()->toString(),
            $invitation->remarks(),
            $invitation->status()->toString(),
            $invitation->createdAt()->format(DATE_ATOM),
            $invitation->expiresAt()->format(DATE_ATOM),
            $invitation->consumedAt() !== null ? $invitation->consumedAt()->format(DATE_ATOM) : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'email' => $this->email,
            'name' => $this->invitedName,
            'invited_by_person_id' => $this->invitedByPersonId,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'consumed_at' => $this->consumedAt,
        ];
    }
}
