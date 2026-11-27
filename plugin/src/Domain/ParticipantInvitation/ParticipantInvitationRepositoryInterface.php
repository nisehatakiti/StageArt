<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionId;

interface ParticipantInvitationRepositoryInterface
{
    public function save(ParticipantInvitation $invitation): void;

    public function findById(ParticipantInvitationId $id): ?ParticipantInvitation;

    /**
     * R14-B §5.4: findById() with an exclusive row lock held until the
     * caller's transaction ends (SELECT ... FOR UPDATE), taken after the
     * Production lock (Production -> invitation -> Participant -> delegate).
     * Throws when the read fails; null only when there is no such row.
     */
    public function findByIdForUpdate(ParticipantInvitationId $id): ?ParticipantInvitation;

    /**
     * R14-B §6.4 / §7.2: the invitations (any Production, any status)
     * addressed to one of the given addresses - the invitee's own verified
     * addresses. A database match is only a candidate: an invitation is
     * returned only when its address equals one of the given ones under
     * InvitationEmailAddress::normalize(), compared in PHP. Throws when the
     * read fails (never an empty list for a failure).
     *
     * @param string[] $emails
     * @return ParticipantInvitation[]
     */
    public function findByVerifiedEmailCandidates(array $emails): array;

    /**
     * R14-B §11.5 (U10): records that the one PrimaryManager notification
     * for this invitation's INVITER_REVOKED is being attempted - a
     * conditional UPDATE that sets revocation_notified_at only while it is
     * NULL. true = this call recorded it (the caller attempts the
     * notification after commit); false = it was already recorded (no
     * second attempt). Called with the invitation row locked. Throws when
     * the write fails.
     */
    public function markRevocationNotificationAttempted(ParticipantInvitationId $id, \DateTimeImmutable $at): bool;

    /**
     * R14-B §5.3 / §5.4 (stage 4): this Production's invitations of the
     * type to the same address (any status), locked FOR UPDATE after the
     * Production lock - the create API decides "resend the PENDING one" or
     * "create a new one" on them. SQL narrows by the column, then only exact
     * §6.4 matches (trim + lower-case) are kept. Throws when the read fails.
     *
     * @return ParticipantInvitation[]
     */
    public function findByProductionEmailAndTypeForUpdate(
        ProductionId $productionId,
        string $email,
        ParticipantType $participantType
    ): array;

    /**
     * R14-B §11.3 step 3 (stage 4): the invitation's current_send_id read
     * again just before a send, without a lock. Throws when the read fails.
     */
    public function currentSendIdOf(ParticipantInvitationId $id): ?string;

    /**
     * All invitations (any status) for a given Production/email/
     * ParticipantType tuple - no DB-level uniqueness is enforced on this
     * tuple (see stageart_participant_invitations's own schema notes),
     * so historical CANCELLED/CONSUMED/expired-but-still-PENDING rows
     * may accumulate alongside the current one. Callers filter for
     * isUsable() themselves (see CreateParticipantInvitationUseCase's
     * duplicate-invitation check).
     *
     * @return ParticipantInvitation[]
     */
    public function findByProductionEmailAndType(
        ProductionId $productionId,
        string $email,
        ParticipantType $participantType
    ): array;

    /**
     * Every invitation (any Production, any status) for a given email.
     * (R14-B stage 4: no application flow resolves invitations by address
     * any more - the auto-resolution was removed; the invitee's own list
     * uses findByVerifiedEmailCandidates().)
     *
     * @return ParticipantInvitation[]
     */
    public function findByEmail(string $email): array;

    /**
     * @return ParticipantInvitation[]
     */
    public function findByProductionId(ProductionId $productionId): array;
}
