<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * StageArt メール招待によるProductionParticipant追加機能 (docs
 * Domain調査・設計ラウンド): a PrimaryManager/PARTICIPANT_MANAGER's
 * already-authorized request to add someone, by email, who has no
 * StageArt Person yet. This Entity never creates a Participant itself -
 * see ResolveParticipantInvitationUseCase, which re-invokes the existing
 * CreateParticipantUseCase (using `invitedByPersonId` as the requester)
 * once the invitee has completed their own, ordinary registration. This
 * keeps Participant creation authorization exactly where it already
 * lives (ProductionAuthorizationService::canManageParticipants()) rather
 * than duplicating or bypassing it here.
 *
 * Deliberately minimal per this round's explicit instruction: no
 * `updatedAt`, `cancelledAt`, `cancelledByPersonId`, `resendCount`, or
 * `lastSentAt` - a resend rotates this same row's token fields in place
 * (see rotateToken()) rather than tracking resend history.
 *
 * One current token per invitation (tokenHash/expiresAt), mirroring
 * EmailVerificationToken/PasswordResetToken's hash-only-storage and
 * expiry shape, but - unlike those two, which are their own separate
 * Entities allowing multiple historical rows per owner - embedded
 * directly on this Entity, since a ParticipantInvitation and its
 * "current token" are a strict 1:1 relationship with no need to keep
 * issuance history.
 */
final class ParticipantInvitation
{
    /**
     * The invitation's validity window. Centralized here (rather than as
     * a private const duplicated on both CreateParticipantInvitationUseCase
     * and ResendParticipantInvitationUseCase, which would need the exact
     * same value) per this round's explicit instruction not to hardcode
     * "24時間" in more than one place - both UseCases compute
     * `expiresAt` from this single constant before calling create()/
     * rotateToken().
     */
    public const TOKEN_LIFETIME_SPEC = 'PT24H';

    private ParticipantInvitationId $id;
    private ProductionId $productionId;
    private string $email;
    private PersonId $invitedByPersonId;
    private ParticipantType $participantType;
    private ?string $remarks;
    private string $tokenHash;
    private ParticipantInvitationStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $expiresAt;
    private ?DateTimeImmutable $consumedAt;

    private function __construct(
        ParticipantInvitationId $id,
        ProductionId $productionId,
        string $email,
        PersonId $invitedByPersonId,
        ParticipantType $participantType,
        ?string $remarks,
        string $tokenHash,
        ParticipantInvitationStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $consumedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->email = $email;
        $this->invitedByPersonId = $invitedByPersonId;
        $this->participantType = $participantType;
        $this->remarks = $remarks;
        $this->tokenHash = $tokenHash;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->consumedAt = $consumedAt;
    }

    public static function create(
        ProductionId $productionId,
        string $email,
        PersonId $invitedByPersonId,
        ParticipantType $participantType,
        ?string $remarks,
        string $tokenHash,
        DateTimeImmutable $expiresAt
    ): self {
        $trimmedEmail = trim($email);

        if ($trimmedEmail === '' || filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email address: {$email}");
        }

        $trimmedRemarks = $remarks !== null ? trim($remarks) : null;

        return new self(
            ParticipantInvitationId::generate(),
            $productionId,
            $trimmedEmail,
            $invitedByPersonId,
            $participantType,
            $trimmedRemarks === null || $trimmedRemarks === '' ? null : $trimmedRemarks,
            $tokenHash,
            ParticipantInvitationStatus::pending(),
            new DateTimeImmutable(),
            $expiresAt,
            null
        );
    }

    public static function reconstitute(
        ParticipantInvitationId $id,
        ProductionId $productionId,
        string $email,
        PersonId $invitedByPersonId,
        ParticipantType $participantType,
        ?string $remarks,
        string $tokenHash,
        ParticipantInvitationStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $consumedAt
    ): self {
        return new self(
            $id,
            $productionId,
            $email,
            $invitedByPersonId,
            $participantType,
            $remarks,
            $tokenHash,
            $status,
            $createdAt,
            $expiresAt,
            $consumedAt
        );
    }

    /** PENDING and not yet expired - mirrors JoinKey::isUsable()'s own
     * dynamic (not physically-written) expiry check. */
    public function isUsable(): bool
    {
        if (! $this->status->equals(ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::PENDING))) {
            return false;
        }

        return $this->expiresAt > new DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return $this->status->toString() === ParticipantInvitationStatus::PENDING;
    }

    /** Called once the invitee's registration has been used to fulfil
     * this invitation (whether a fresh Participant was created, or one
     * already existed - see ResolveParticipantInvitationUseCase). Only
     * checks status, not expiry - the caller (ResolveParticipantInvitationUseCase)
     * is responsible for checking isUsable() (status AND expiry) before
     * ever calling this, the same "caller checks isUsable(), narrower
     * mutators check only their own invariant" split JoinKey::recordUse()
     * already uses. */
    public function consume(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be consumed.');
        }

        $this->status = ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::CONSUMED);
        $this->consumedAt = new DateTimeImmutable();
    }

    public function cancel(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be cancelled.');
        }

        $this->status = ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::CANCELLED);
    }

    /** Resend: replaces this invitation's current token in place - no new
     * row, no resend-history fields (this round's explicit minimal-
     * fields instruction). Allowed even if the previous token already
     * expired (status is still PENDING - expiry is dynamic, never
     * physically written), which is exactly what lets a resend revive an
     * otherwise-inert expired invitation. */
    public function rotateToken(string $newTokenHash, DateTimeImmutable $newExpiresAt): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be resent.');
        }

        $this->tokenHash = $newTokenHash;
        $this->expiresAt = $newExpiresAt;
    }

    public function id(): ParticipantInvitationId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function invitedByPersonId(): PersonId
    {
        return $this->invitedByPersonId;
    }

    public function participantType(): ParticipantType
    {
        return $this->participantType;
    }

    public function remarks(): ?string
    {
        return $this->remarks;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function status(): ParticipantInvitationStatus
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
