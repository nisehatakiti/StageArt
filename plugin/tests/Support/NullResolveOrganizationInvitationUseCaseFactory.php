<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\OrganizationInvitation\ResolveOrganizationInvitationUseCase;

/**
 * Mirrors NullResolveParticipantInvitationUseCaseFactory's own purpose:
 * Authentication test files now require a
 * ResolveOrganizationInvitationUseCase, but none of those tests create
 * any OrganizationInvitation, so it is always a no-op there.
 */
final class NullResolveOrganizationInvitationUseCaseFactory
{
    public static function create(): ResolveOrganizationInvitationUseCase
    {
        return new ResolveOrganizationInvitationUseCase(
            new InMemoryOrganizationInvitationRepository(),
            new InMemoryMembershipRepository()
        );
    }
}
