<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;

/** Unauthenticated (no requester) - mirrors
 * GetParticipantInvitationByTokenUseCase's own shape, including treating
 * expired/consumed/cancelled/unknown tokens identically as "not found"
 * (mapped to a 404 by the REST controller). */
final class GetOrganizationInvitationByTokenUseCase
{
    private OrganizationInvitationRepositoryInterface $invitations;
    private OrganizationRepositoryInterface $organizations;

    public function __construct(
        OrganizationInvitationRepositoryInterface $invitations,
        OrganizationRepositoryInterface $organizations
    ) {
        $this->invitations = $invitations;
        $this->organizations = $organizations;
    }

    public function execute(GetOrganizationInvitationByTokenQuery $query): OrganizationInvitationPreviewResult
    {
        $tokenHash = hash('sha256', $query->token);
        $invitation = $this->invitations->findByTokenHash($tokenHash);

        if (! $invitation || ! $invitation->isUsable()) {
            throw new OrganizationInvitationNotFoundException('No usable OrganizationInvitation found for this token.');
        }

        $organization = $this->organizations->findById($invitation->organizationId());

        if (! $organization) {
            throw new OrganizationInvitationNotFoundException('No usable OrganizationInvitation found for this token.');
        }

        return new OrganizationInvitationPreviewResult(
            $organization->name()->toString(),
            $invitation->email(),
            $invitation->invitedName(),
            $invitation->status()->toString()
        );
    }
}
