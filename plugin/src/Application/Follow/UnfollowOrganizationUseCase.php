<?php

declare(strict_types=1);

namespace StageArt\Application\Follow;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Follow\OrganizationFollowRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;

/**
 * Idempotent: unfollowing an Organization the Person never followed (or
 * already unfollowed) is a no-op success, not an error - matching
 * Follow.md's "既存のMembershipやParticipantには影響しない" framing of
 * Unfollow as a simple state change, not an operation with preconditions
 * to violate. Deliberately does not require the Organization to still
 * exist/be published (unlike FollowOrganizationUseCase) - a Person must
 * always be able to clear their own Follow state.
 */
final class UnfollowOrganizationUseCase
{
    private OrganizationFollowRepositoryInterface $follows;
    private OrganizationAuthorizationService $authorization;

    public function __construct(OrganizationFollowRepositoryInterface $follows, OrganizationAuthorizationService $authorization)
    {
        $this->follows = $follows;
        $this->authorization = $authorization;
    }

    public function execute(UnfollowOrganizationCommand $command): OrganizationFollowStatusResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new FollowAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);
        $existing = $this->follows->findByPersonAndOrganization($person->id(), $organizationId);

        if ($existing && $existing->isActive()) {
            $existing->unfollow();
            $this->follows->save($existing);
        }

        return new OrganizationFollowStatusResult(
            $organizationId->toString(),
            false,
            $this->follows->countActiveByOrganizationId($organizationId)
        );
    }
}
