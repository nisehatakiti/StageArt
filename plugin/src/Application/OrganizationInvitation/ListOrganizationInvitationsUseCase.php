<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * Mirrors ListParticipantInvitationsUseCase's own shape - needed so
 * メンバー管理 can show PENDING OrganizationInvitations alongside the
 * roster, matching Production's メンバー管理 screen (招待中のメンバー).
 */
final class ListOrganizationInvitationsUseCase
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

    /**
     * @return OrganizationInvitationResult[]
     */
    public function execute(ListOrganizationInvitationsQuery $query): array
    {
        $organizationId = OrganizationId::fromString($query->organizationId);
        $organization = $this->organizations->findById($organizationId);

        if (! $organization) {
            throw new OrganizationNotFoundException($query->organizationId);
        }

        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        if (! $this->authorization->hasRole($requester, $organizationId, [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can view invitations.');
        }

        return array_map(
            static fn ($invitation) => OrganizationInvitationResult::fromDomain($invitation),
            $this->invitations->findByOrganizationId($organizationId)
        );
    }
}
