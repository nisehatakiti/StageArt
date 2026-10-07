<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationId;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/** Mirrors CancelParticipantInvitationUseCase's own shape - status ->
 * CANCELLED only, tokenHash left as-is (see OrganizationInvitation::isUsable()). */
final class CancelOrganizationInvitationUseCase
{
    private OrganizationInvitationRepositoryInterface $invitations;
    private OrganizationRepositoryInterface $organizations;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        OrganizationInvitationRepositoryInterface $invitations,
        OrganizationRepositoryInterface $organizations,
        OrganizationAuthorizationService $authorization
    ) {
        $this->invitations = $invitations;
        $this->organizations = $organizations;
        $this->authorization = $authorization;
    }

    public function execute(CancelOrganizationInvitationCommand $command): OrganizationInvitationResult
    {
        $invitation = $this->invitations->findById(OrganizationInvitationId::fromString($command->invitationId));

        if (! $invitation) {
            throw new OrganizationInvitationNotFoundException($command->invitationId);
        }

        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organization = $this->organizations->findById($invitation->organizationId());

        if (! $organization) {
            throw new OrganizationNotFoundException($invitation->organizationId()->toString());
        }

        if (! $this->authorization->hasRole($requester, $invitation->organizationId(), [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can cancel this invitation.');
        }

        if (! $invitation->isPending()) {
            throw new OrganizationInvitationNotPendingException('Only a PENDING OrganizationInvitation can be cancelled.');
        }

        $invitation->cancel();
        $this->invitations->save($invitation);

        return OrganizationInvitationResult::fromDomain($invitation);
    }
}
