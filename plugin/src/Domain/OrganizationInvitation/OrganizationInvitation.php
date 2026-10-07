<?php

declare(strict_types=1);

namespace StageArt\Domain\OrganizationInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

/**
 * StageArt 団体管理UI整理 "メンバーを追加": the Organization-Scope
 * counterpart of ParticipantInvitation, same shape and lifecycle minus
 * `participantType` - Organization membership carries no per-invite Role
 * choice at add-time (every accepted invitation becomes a plain MEMBER
 * Membership; see CreateOrganizationInvitationUseCase/
 * ResolveOrganizationInvitationUseCase). This Entity never creates a
 * Membership itself - ResolveOrganizationInvitationUseCase re-invokes
 * Membership::create() directly once the invitee completes their own
 * registration, the same "resolve via the existing creation path" shape
 * ResolveParticipantInvitationUseCase already uses.
 */
final class OrganizationInvitation
{
    public const TOKEN_LIFETIME_SPEC = 'PT24H';

    private OrganizationInvitationId $id;
    private OrganizationId $organizationId;
    private string $email;
    private string $invitedName;
    private PersonId $invitedByPersonId;
    private ?string $remarks;
    private string $tokenHash;
    private OrganizationInvitationStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $expiresAt;
    private ?DateTimeImmutable $consumedAt;

    private function __construct(
        OrganizationInvitationId $id,
        OrganizationId $organizationId,
        string $email,
        string $invitedName,
        PersonId $invitedByPersonId,
        ?string $remarks,
        string $tokenHash,
        OrganizationInvitationStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $consumedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->email = $email;
        $this->invitedName = $invitedName;
        $this->invitedByPersonId = $invitedByPersonId;
        $this->remarks = $remarks;
        $this->tokenHash = $tokenHash;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->consumedAt = $consumedAt;
    }

    public static function create(
        OrganizationId $organizationId,
        string $email,
        string $invitedName,
        PersonId $invitedByPersonId,
        ?string $remarks,
        string $tokenHash,
        DateTimeImmutable $expiresAt
    ): self {
        $trimmedEmail = trim($email);

        if ($trimmedEmail === '' || filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email address: {$email}");
        }

        $trimmedName = trim($invitedName);

        if ($trimmedName === '') {
            throw new InvalidArgumentException('invitedName must not be empty.');
        }

        $trimmedRemarks = $remarks !== null ? trim($remarks) : null;

        return new self(
            OrganizationInvitationId::generate(),
            $organizationId,
            $trimmedEmail,
            $trimmedName,
            $invitedByPersonId,
            $trimmedRemarks === null || $trimmedRemarks === '' ? null : $trimmedRemarks,
            $tokenHash,
            OrganizationInvitationStatus::pending(),
            new DateTimeImmutable(),
            $expiresAt,
            null
        );
    }

    public static function reconstitute(
        OrganizationInvitationId $id,
        OrganizationId $organizationId,
        string $email,
        string $invitedName,
        PersonId $invitedByPersonId,
        ?string $remarks,
        string $tokenHash,
        OrganizationInvitationStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $consumedAt
    ): self {
        return new self(
            $id,
            $organizationId,
            $email,
            $invitedName,
            $invitedByPersonId,
            $remarks,
            $tokenHash,
            $status,
            $createdAt,
            $expiresAt,
            $consumedAt
        );
    }

    public function isUsable(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->expiresAt > new DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return $this->status->toString() === OrganizationInvitationStatus::PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new DateTimeImmutable();
    }

    public function consume(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING OrganizationInvitation can be consumed.');
        }

        $this->status = OrganizationInvitationStatus::fromString(OrganizationInvitationStatus::CONSUMED);
        $this->consumedAt = new DateTimeImmutable();
    }

    public function cancel(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING OrganizationInvitation can be cancelled.');
        }

        $this->status = OrganizationInvitationStatus::fromString(OrganizationInvitationStatus::CANCELLED);
    }

    public function rotateToken(string $newTokenHash, DateTimeImmutable $newExpiresAt): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING OrganizationInvitation can be resent.');
        }

        $this->tokenHash = $newTokenHash;
        $this->expiresAt = $newExpiresAt;
    }

    public function id(): OrganizationInvitationId
    {
        return $this->id;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function invitedName(): string
    {
        return $this->invitedName;
    }

    public function invitedByPersonId(): PersonId
    {
        return $this->invitedByPersonId;
    }

    public function remarks(): ?string
    {
        return $this->remarks;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function status(): OrganizationInvitationStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function consumedAt(): ?DateTimeImmutable
    {
        return $this->consumedAt;
    }
}
