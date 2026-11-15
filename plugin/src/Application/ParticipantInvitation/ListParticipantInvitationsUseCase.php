<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Not explicitly named in this round's instruction (§9's minimum
 * UseCase list), but added to support §24's requirement that the member
 * management screen can show current PENDING invitations - there is no
 * other way to list a Production's invitations. Disclosed here as an
 * addition beyond the literal instruction, per this round's own
 * "既存仕様にない変更を行った場合は、必ずその理由と変更内容を明記してください"
 * requirement.
 */
final class ListParticipantInvitationsUseCase
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

    /**
     * @return ParticipantInvitationResult[]
     */
    public function execute(ListParticipantInvitationsQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ParticipantAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canManageParticipants($requester, $production)) {
            throw new ParticipantAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PARTICIPANT_MANAGER Role can view invitations.'
            );
        }

        return array_map(
            static fn ($invitation) => ParticipantInvitationResult::fromDomain($invitation),
            $this->invitations->findByProductionId($production->id())
        );
    }
}
