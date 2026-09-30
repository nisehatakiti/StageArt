<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;

/**
 * Never carries the raw token or its hash (§19: "tokenそのものをレスポンス
 * に返さないでください" - this Result is reused for the create/resend/
 * cancel/list REST responses too, so the same rule applies uniformly).
 */
final class ParticipantInvitationResult
{
    public string $id;
    public string $productionId;
    public string $email;
    public string $invitedName;
    public string $invitedByPersonId;
    public string $participantType;
    public ?string $remarks;
    public string $status;
    public string $createdAt;
    public string $expiresAt;
    public ?string $consumedAt;
    public bool $isExpired;

    public function __construct(
        string $id,
        string $productionId,
        string $email,
        string $invitedName,
        string $invitedByPersonId,
        string $participantType,
        ?string $remarks,
        string $status,
        string $createdAt,
        string $expiresAt,
        ?string $consumedAt,
        bool $isExpired
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->email = $email;
        $this->invitedName = $invitedName;
        $this->invitedByPersonId = $invitedByPersonId;
        $this->participantType = $participantType;
        $this->remarks = $remarks;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->consumedAt = $consumedAt;
        $this->isExpired = $isExpired;
    }

    public static function fromDomain(ParticipantInvitation $invitation): self
    {
        return new self(
            $invitation->id()->toString(),
            $invitation->productionId()->toString(),
            $invitation->email(),
            $invitation->invitedName(),
            $invitation->invitedByPersonId()->toString(),
            $invitation->participantType()->toString(),
            $invitation->remarks(),
            $invitation->status()->toString(),
            $invitation->createdAt()->format(DATE_ATOM),
            $invitation->expiresAt()->format(DATE_ATOM),
            $invitation->consumedAt() !== null ? $invitation->consumedAt()->format(DATE_ATOM) : null,
            $invitation->isExpired()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'production_id' => $this->productionId,
            'email' => $this->email,
            'name' => $this->invitedName,
            'invited_by_person_id' => $this->invitedByPersonId,
            'participant_type' => $this->participantType,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'consumed_at' => $this->consumedAt,
            'is_expired' => $this->isExpired,
        ];
    }
}
