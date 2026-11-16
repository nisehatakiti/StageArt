<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Participant\ParticipantId;
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
 *
 * R14-B (docs/handoffs/PR7-R14B-ParticipantInvitationDesign.md §5.1): the
 * invitation now records the invitee's own response (ACCEPTED / REJECTED
 * with inviteePersonId, respondedAt, participantId) and a past
 * invalidation (closeReason / closedAt / closedByPersonId, written once).
 * The token above is no longer used to look invitations up (U5 C案); the
 * auto-resolution described above is retired by the Application layer in
 * a later step.
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
    private string $invitedName;
    private PersonId $invitedByPersonId;
    private ParticipantType $participantType;
    private ?string $remarks;
    private string $tokenHash;
    private ParticipantInvitationStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $expiresAt;
    private ?DateTimeImmutable $consumedAt;
    /** R14-B §6.1: the Person who accepted/rejected (recorded on response). */
    private ?PersonId $inviteePersonId;
    /** R14-B §6.1: when the invitee accepted/rejected. */
    private ?DateTimeImmutable $respondedAt;
    /** R14-B §6.1: the Participant created/re-enrolled/consented by acceptance. */
    private ?ParticipantId $participantId;
    /** R14-B §5.1: past invalidation - written once, never overwritten. */
    private ?ParticipantInvitationCloseReason $closeReason;
    private ?DateTimeImmutable $closedAt;
    /** R14-B §5.1: only for MANAGER (the manager who cancelled); NULL for system closes. */
    private ?PersonId $closedByPersonId;

    private function __construct(
        ParticipantInvitationId $id,
        ProductionId $productionId,
        string $email,
        string $invitedName,
        PersonId $invitedByPersonId,
        ParticipantType $participantType,
        ?string $remarks,
        string $tokenHash,
        ParticipantInvitationStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $consumedAt,
        ?PersonId $inviteePersonId = null,
        ?DateTimeImmutable $respondedAt = null,
        ?ParticipantId $participantId = null,
        ?ParticipantInvitationCloseReason $closeReason = null,
        ?DateTimeImmutable $closedAt = null,
        ?PersonId $closedByPersonId = null
    ) {
        if (($closeReason === null) !== ($closedAt === null)) {
            throw new InvalidArgumentException('closeReason and closedAt must be both set or both null.');
        }

        $this->inviteePersonId = $inviteePersonId;
        $this->respondedAt = $respondedAt;
        $this->participantId = $participantId;
        $this->closeReason = $closeReason;
        $this->closedAt = $closedAt;
        $this->closedByPersonId = $closedByPersonId;
        $this->id = $id;
        $this->productionId = $productionId;
        $this->email = $email;
        $this->invitedName = $invitedName;
        $this->invitedByPersonId = $invitedByPersonId;
        $this->participantType = $participantType;
        $this->remarks = $remarks;
        $this->tokenHash = $tokenHash;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->consumedAt = $consumedAt;
    }

    /**
     * StageArt Production メンバー追加(氏名＋メールアドレス統一)ラウンド
     * §3: `invitedName` is required (not nullable) - this Entity only
     * ever gets created from the admin's "氏名＋メールアドレス" member-add
     * form (§1/§2), which collects a name unconditionally, unlike
     * `remarks` which stays optional. Used as the registration screen's
     * initial name value (§3's "登録画面では...初期値として利用できるように
     * する") - never written back to the eventual real Person.
     */
    public static function create(
        ProductionId $productionId,
        string $email,
        string $invitedName,
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

        $trimmedName = trim($invitedName);

        if ($trimmedName === '') {
            throw new InvalidArgumentException('invitedName must not be empty.');
        }

        $trimmedRemarks = $remarks !== null ? trim($remarks) : null;

        return new self(
            ParticipantInvitationId::generate(),
            $productionId,
            $trimmedEmail,
            $trimmedName,
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

    /**
     * The trailing R14-B arguments default to NULL so the current
     * Repository (no columns for them yet) keeps reconstituting rows
     * exactly as before.
     */
    public static function reconstitute(
        ParticipantInvitationId $id,
        ProductionId $productionId,
        string $email,
        string $invitedName,
        PersonId $invitedByPersonId,
        ParticipantType $participantType,
        ?string $remarks,
        string $tokenHash,
        ParticipantInvitationStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $consumedAt,
        ?PersonId $inviteePersonId = null,
        ?DateTimeImmutable $respondedAt = null,
        ?ParticipantId $participantId = null,
        ?ParticipantInvitationCloseReason $closeReason = null,
        ?DateTimeImmutable $closedAt = null,
        ?PersonId $closedByPersonId = null
    ): self {
        return new self(
            $id,
            $productionId,
            $email,
            $invitedName,
            $invitedByPersonId,
            $participantType,
            $remarks,
            $tokenHash,
            $status,
            $createdAt,
            $expiresAt,
            $consumedAt,
            $inviteePersonId,
            $respondedAt,
            $participantId,
            $closeReason,
            $closedAt,
            $closedByPersonId
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

    /** Same rule as isExpired(), against an explicit instant (instants compare timezone-independently). */
    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    /**
     * R14-B §5.1 / §7.2 accept: the invitee themselves accepts. Only a
     * PENDING invitation within its expiry can be accepted; CANCELLED (any
     * reason), REJECTED and the legacy CONSUMED cannot. Whether the
     * production is closed or the inviter still has permission is decided
     * by the Application layer BEFORE calling this (it closes the
     * invitation with close() instead). Repeating the acceptance by the
     * same Person is an idempotent no-op.
     *
     * @return bool true when the invitation actually changed
     */
    public function accept(PersonId $inviteePersonId, ParticipantId $participantId, ?DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new DateTimeImmutable();

        if ($this->status->toString() === ParticipantInvitationStatus::ACCEPTED) {
            if ($this->inviteePersonId !== null && $this->inviteePersonId->equals($inviteePersonId)) {
                return false;
            }

            throw new InvalidArgumentException('This ParticipantInvitation was already accepted by someone else.');
        }

        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be accepted.');
        }

        if ($this->isExpiredAt($now)) {
            throw new InvalidArgumentException('An expired ParticipantInvitation cannot be accepted.');
        }

        $this->status = ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::ACCEPTED);
        $this->inviteePersonId = $inviteePersonId;
        $this->respondedAt = $now;
        $this->participantId = $participantId;

        return true;
    }

    /**
     * R14-B §4.3 / §5.1 / §7.2 reject (U7): the invitee themselves declines.
     * Allowed, within the expiry, from:
     * - PENDING (including when the production is closed or the inviter
     *   lost permission - pass that as `$firstDetectedCloseReason` when the
     *   reject is the write that first detects it, so it is recorded as the
     *   past invalidation without overwriting anything), and
     * - CANCELLED(PRODUCTION_CLOSED / INVITER_REVOKED), keeping the original
     *   close reason and time.
     * Not allowed from CANCELLED(MANAGER), a reason-less (pre-R14-B)
     * CANCELLED, ACCEPTED or CONSUMED, nor after expiry. Repeating the
     * rejection by the same Person is an idempotent no-op.
     *
     * @return bool true when the invitation actually changed
     */
    public function reject(
        PersonId $inviteePersonId,
        ?ParticipantInvitationCloseReason $firstDetectedCloseReason = null,
        ?DateTimeImmutable $now = null
    ): bool {
        $now = $now ?? new DateTimeImmutable();
        $status = $this->status->toString();

        if ($status === ParticipantInvitationStatus::REJECTED) {
            if ($this->inviteePersonId !== null && $this->inviteePersonId->equals($inviteePersonId)) {
                return false;
            }

            throw new InvalidArgumentException('This ParticipantInvitation was already rejected by someone else.');
        }

        if ($status === ParticipantInvitationStatus::PENDING) {
            if ($firstDetectedCloseReason !== null && ! $firstDetectedCloseReason->allowsInviteeRejection()) {
                throw new InvalidArgumentException('Only PRODUCTION_CLOSED or INVITER_REVOKED can be detected at reject time.');
            }
        } elseif ($status === ParticipantInvitationStatus::CANCELLED
            && $this->closeReason !== null
            && $this->closeReason->allowsInviteeRejection()
        ) {
            if ($firstDetectedCloseReason !== null) {
                throw new InvalidArgumentException('A recorded close reason is never overwritten.');
            }
        } else {
            throw new InvalidArgumentException("A {$status} ParticipantInvitation cannot be rejected.");
        }

        if ($this->isExpiredAt($now)) {
            throw new InvalidArgumentException('An expired ParticipantInvitation cannot be rejected.');
        }

        if ($firstDetectedCloseReason !== null) {
            $this->closeReason = $firstDetectedCloseReason;
            $this->closedAt = $now;
        }

        $this->status = ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::REJECTED);
        $this->inviteePersonId = $inviteePersonId;
        $this->respondedAt = $now;

        return true;
    }

    /**
     * R14-B §5.1 invalidation: PENDING → CANCELLED with the reason recorded
     * once (with `closedAt`); a recorded reason is never overwritten.
     * MANAGER requires the cancelling manager (`closedByPersonId`) and is
     * idempotent on an invitation already CANCELLED(MANAGER); the system
     * reasons (PRODUCTION_CLOSED / INVITER_REVOKED) take no actor.
     * Expiry does not matter here (a manager may cancel an expired PENDING
     * invitation). The legacy cancel() (no reason) is left as it is until
     * the Application layer is switched over.
     *
     * @return bool true when the invitation actually changed
     */
    public function close(
        ParticipantInvitationCloseReason $reason,
        ?PersonId $closedByPersonId = null,
        ?DateTimeImmutable $now = null
    ): bool {
        $isManager = ! $reason->allowsInviteeRejection();

        if ($isManager && $closedByPersonId === null) {
            throw new InvalidArgumentException('A MANAGER close requires the cancelling manager.');
        }

        if (! $isManager && $closedByPersonId !== null) {
            throw new InvalidArgumentException('A system close (PRODUCTION_CLOSED / INVITER_REVOKED) takes no actor.');
        }

        if ($isManager
            && $this->status->toString() === ParticipantInvitationStatus::CANCELLED
            && $this->closeReason !== null
            && $this->closeReason->equals($reason)
        ) {
            return false;
        }

        if ($this->closeReason !== null) {
            throw new InvalidArgumentException('A recorded close reason is never overwritten.');
        }

        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be closed.');
        }

        $this->status = ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::CANCELLED);
        $this->closeReason = $reason;
        $this->closedAt = $now ?? new DateTimeImmutable();
        $this->closedByPersonId = $closedByPersonId;

        return true;
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

    public function invitedName(): string
    {
        return $this->invitedName;
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

    public function inviteePersonId(): ?PersonId
    {
        return $this->inviteePersonId;
    }

    public function respondedAt(): ?DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function participantId(): ?ParticipantId
    {
        return $this->participantId;
    }

    public function closeReason(): ?ParticipantInvitationCloseReason
    {
        return $this->closeReason;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedByPersonId(): ?PersonId
    {
        return $this->closedByPersonId;
    }
}
