<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionId;

interface ParticipantInvitationRepositoryInterface
{
    public function save(ParticipantInvitation $invitation): void;

    public function findById(ParticipantInvitationId $id): ?ParticipantInvitation;

    public function findByTokenHash(string $tokenHash): ?ParticipantInvitation;

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
     * Every invitation (any Production, any status) for a given email -
     * used by ResolveParticipantInvitationUseCase to resolve every
     * outstanding invitation a freshly-registered email might satisfy,
     * not just one Production's.
     *
     * @return ParticipantInvitation[]
     */
    public function findByEmail(string $email): array;

    /**
     * @return ParticipantInvitation[]
     */
    public function findByProductionId(ProductionId $productionId): array;
}
