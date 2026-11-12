<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * §19: unauthenticated (no requester). An expired, consumed, cancelled,
 * or unknown token are all treated identically as "not found" - per
 * this round's explicit instruction not to introduce a new HTTP status
 * code (e.g. 410 Gone, unused anywhere else in this codebase), the REST
 * Controller maps ParticipantInvitationNotFoundException to the same
 * 404 this codebase already uses everywhere else for "not found".
 */
final class GetParticipantInvitationByTokenUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
    }

    public function execute(GetParticipantInvitationByTokenQuery $query): ParticipantInvitationPreviewResult
    {
        $tokenHash = hash('sha256', $query->token);
        $invitation = $this->invitations->findByTokenHash($tokenHash);

        if (! $invitation || ! $invitation->isUsable()) {
            throw new ParticipantInvitationNotFoundException('No usable ParticipantInvitation found for this token.');
        }

        $production = $this->productions->findById($invitation->productionId());

        if (! $production) {
            throw new ParticipantInvitationNotFoundException('No usable ParticipantInvitation found for this token.');
        }

        return new ParticipantInvitationPreviewResult(
            $production->name()->toString(),
            $invitation->email(),
            $invitation->invitedName(),
            $invitation->participantType()->toString(),
            $invitation->status()->toString()
        );
    }
}
