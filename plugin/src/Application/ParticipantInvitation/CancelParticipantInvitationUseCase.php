<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * §21: status -> CANCELLED only. No Participant is ever created for a
 * ParticipantInvitation (this round's confirmed design), so there is
 * nothing to delete here - and tokenHash is left as-is (token
 * verification checks status, not whether the hash column itself was
 * cleared - see ParticipantInvitation::isUsable()).
 */
final class CancelParticipantInvitationUseCase
{
    private ParticipantInvitationRepositoryInterface $invitations;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ParticipantInvitationRepositoryInterface $invitations,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->invitations = $invitations;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(CancelParticipantInvitationCommand $command): ParticipantInvitationResult
    {
        $invitation = $this->invitations->findById(ParticipantInvitationId::fromString($command->invitationId));

        if (! $invitation) {
            throw new ParticipantInvitationNotFoundException($command->invitationId);
        }

        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantInvitationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById($invitation->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($invitation->productionId()->toString());
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantInvitationAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can cancel this invitation.'
            );
        }

        if (! $invitation->isPending()) {
            throw new ParticipantInvitationNotPendingException(
                'Only a PENDING ParticipantInvitation can be cancelled.'
            );
        }

        $invitation->cancel();
        $this->invitations->save($invitation);

        return ParticipantInvitationResult::fromDomain($invitation);
    }
}
