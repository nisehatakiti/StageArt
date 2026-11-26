<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionStatus;

/**
 * R14-B §4.3 / §5.1: the two outside conditions an invitation depends on,
 * judged the same way by accept, reject and the invitee's list.
 *
 * - The Production is closed when it is COMPLETED / ARCHIVED / CANCELLED (D5).
 * - The inviter still has permission when they still pass
 *   canManageParticipants() on the Production at this moment (D3). An
 *   inviter Person that no longer exists has none.
 */
final class ParticipantInvitationConditions
{
    private const CLOSED_PRODUCTION_STATUSES = [
        ProductionStatus::COMPLETED,
        ProductionStatus::ARCHIVED,
        ProductionStatus::CANCELLED,
    ];

    private ProductionAuthorizationService $authorization;
    private PersonRepositoryInterface $people;

    public function __construct(ProductionAuthorizationService $authorization, PersonRepositoryInterface $people)
    {
        $this->authorization = $authorization;
        $this->people = $people;
    }

    public function isProductionClosed(Production $production): bool
    {
        return in_array($production->status()->toString(), self::CLOSED_PRODUCTION_STATUSES, true);
    }

    public function inviterCanManage(ParticipantInvitation $invitation, Production $production): bool
    {
        $inviter = $this->people->findById($invitation->invitedByPersonId());

        return $inviter !== null && $this->authorization->canManageParticipants($inviter, $production);
    }
}
