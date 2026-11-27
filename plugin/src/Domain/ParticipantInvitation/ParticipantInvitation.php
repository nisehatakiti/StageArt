<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * StageArt メール招待によるProductionParticipant追加機能 (docs
 * Domain調査・設計ラウンド): a PrimaryManager/PARTICIPANT_MANAGER's
 * already-authorized request to add someone, by email. This Entity never
 * creates a Participant itself. (R14-B: the invitee accepts explicitly -
 * AcceptParticipantInvitationUseCase; the former auto-resolution on
 * registration was removed in stage 4.)
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
     *
     * R14-B (design doc D4, §6.1): 7 days (was 'PT24H'). A resend moves the
     * expiry to 7 days from the resend. Existing invitations keep the
     * expires_at they were created with (no data change).
     */
    public const TOKEN_LIFETIME_SPEC = 'P7D';

    /** R14-B (design doc D6, §5.3): resends per invitation, not counting the first send (max 6 sends in total). */
    public const MAX_RESENDS = 5;

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
    /** R14-B §6.1 (D6): resends accepted for this invitation (the first send is not counted). */
    private int $resendCount;
    /**
     * R14-B stage 3 (updated_at, schema 1.49.0): when this invitation was
     * last actually changed - set on creation and by every change below
     * (accept, reject, close, resend, ...), never by an idempotent repeat
     * that changes nothing, and never by merely saving it. NULL = a
     * pre-R14-B row that has not changed since (kept NULL; nothing fills it
     * in).
     */
    private ?DateTimeImmutable $updatedAt;
    /**
     * R14-B §6.1 / §11.3 (stage 4): the latest send attempt accepted for
     * this invitation (stageart_participant_invitation_sends.id) and when.
     * Changed only under the Production -> invitation locks by
     * recordSendAccepted(); an older attempt's result never moves it.
     */
    private ?string $currentSendId;
    private ?DateTimeImmutable $lastSentAt;

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
        ?PersonId $closedByPersonId = null,
        int $resendCount = 0,
        ?DateTimeImmutable $updatedAt = null,
        ?string $currentSendId = null,
        ?DateTimeImmutable $lastSentAt = null
    ) {
        if (($closeReason === null) !== ($closedAt === null)) {
            throw new InvalidArgumentException('closeReason and closedAt must be both set or both null.');
        }

        if ($resendCount < 0) {
            throw new InvalidArgumentException('resendCount must not be negative.');
        }

        $this->resendCount = $resendCount;
        $this->updatedAt = $updatedAt;
        $this->currentSendId = $currentSendId;
        $this->lastSentAt = $lastSentAt;
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
        $now = new DateTimeImmutable();

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
            $now,
            $expiresAt,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            0,
            $now
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
        ?PersonId $closedByPersonId = null,
        int $resendCount = 0,
        ?DateTimeImmutable $updatedAt = null,
        ?string $currentSendId = null,
        ?DateTimeImmutable $lastSentAt = null
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
            $closedByPersonId,
            $resendCount,
            $updatedAt,
            $currentSendId,
            $lastSentAt
        );
    }

    /** R14-B (D4): the expiry of an invitation sent (or resent) at `$sentAt` - 7 days later. */
    public static function expiresAtFrom(DateTimeImmutable $sentAt): DateTimeImmutable
    {
        return $sentAt->add(new DateInterval(self::TOKEN_LIFETIME_SPEC));
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
     * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md,
     * 2026-11-27): the invitation is completed by registering the invited
     * Person - no acceptance by the person. Only a PENDING invitation within
     * its expiry can be completed; whether the production is closed or the
     * inviter still has permission is decided by the Application layer
     * BEFORE calling this (it closes the invitation with close() instead).
     * The invitation becomes CONSUMED and records who was registered and as
     * which Participant row. Past ACCEPTED / REJECTED rows (the R14-B
     * acceptance and rejection, removed) are kept as they are.
     */
    public function completeByEnrollment(PersonId $inviteePersonId, ParticipantId $participantId, ?DateTimeImmutable $now = null): void
    {
        $now = $now ?? new DateTimeImmutable();

        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be completed.');
        }

        if ($this->isExpiredAt($now)) {
            throw new InvalidArgumentException('An expired ParticipantInvitation cannot be completed.');
        }

        $this->status = ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::CONSUMED);
        $this->consumedAt = $now;
        $this->inviteePersonId = $inviteePersonId;
        $this->participantId = $participantId;
        $this->updatedAt = $now;
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
        $this->updatedAt = $this->closedAt;

        return true;
    }

    /** Legacy (pre-R14-B auto-resolution, removed in stage 4): CONSUMED
     * stays a terminal state of existing rows; no current flow calls this.
     * Only checks status, not expiry. */
    public function consume(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be consumed.');
        }

        $this->status = ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::CONSUMED);
        $this->consumedAt = new DateTimeImmutable();
        $this->updatedAt = $this->consumedAt;
    }

    public function cancel(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be cancelled.');
        }

        $this->status = ParticipantInvitationStatus::fromString(ParticipantInvitationStatus::CANCELLED);
        $this->updatedAt = new DateTimeImmutable();
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
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * R14-B resend (D4, D6, §5.3): a PENDING invitation (an expired one
     * included - that is how a resend revives it) is resent: the resend is
     * counted and the expiry moves to 7 days from now. The token is NOT
     * changed (U5 C案: the invitation e-mail carries no token). At most
     * MAX_RESENDS resends per invitation; the per-address 30-day cap, the
     * production state and the inviter's permission are checked by the
     * Application layer, not here.
     */
    public function resend(?DateTimeImmutable $now = null): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be resent.');
        }

        if ($this->hasReachedResendLimit()) {
            throw new InvalidArgumentException('This ParticipantInvitation has reached its resend limit.');
        }

        $now = $now ?? new DateTimeImmutable();
        $this->resendCount++;
        $this->expiresAt = self::expiresAtFrom($now);
        $this->updatedAt = $now;
    }

    /**
     * R14-B §11.3 step 1 (stage 4): a send attempt was accepted for this
     * (PENDING) invitation - the initial send or a resend (resend() first).
     * Called under the Production -> invitation locks only.
     */
    public function recordSendAccepted(string $sendId, DateTimeImmutable $at): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING ParticipantInvitation can be sent.');
        }

        $this->currentSendId = $sendId;
        $this->lastSentAt = $at;
        $this->updatedAt = $at;
    }

    public function currentSendId(): ?string
    {
        return $this->currentSendId;
    }

    public function lastSentAt(): ?DateTimeImmutable
    {
        return $this->lastSentAt;
    }

    public function hasReachedResendLimit(): bool
    {
        return $this->resendCount >= self::MAX_RESENDS;
    }

    public function remainingResends(): int
    {
        return max(0, self::MAX_RESENDS - $this->resendCount);
    }

    public function resendCount(): int
    {
        return $this->resendCount;
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

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
