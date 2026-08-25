<?php

declare(strict_types=1);

namespace StageArt\Application\Follow;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Follow\OrganizationFollow;
use StageArt\Domain\Follow\OrganizationFollowRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

/**
 * docs/05-UseCase/FollowAndHomeFeed.md: "Precondition: 対象Organizationの
 * 公開ページを閲覧できること" - an unpublished Organization is treated as
 * not found here, the same way GetPublicOrganizationBySlugUseCase treats
 * unpublished as not-found, never leaking its existence. Deliberately
 * does NOT go through OrganizationAuthorizationService's Membership/Role
 * check - Follow.md is explicit that "OrganizationやProductionに参加して
 * いない一般観客でもFollowを利用できる".
 */
final class FollowOrganizationUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private OrganizationFollowRepositoryInterface $follows;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        OrganizationFollowRepositoryInterface $follows,
        OrganizationAuthorizationService $authorization
    ) {
        $this->organizations = $organizations;
        $this->follows = $follows;
        $this->authorization = $authorization;
    }

    public function execute(FollowOrganizationCommand $command): OrganizationFollowStatusResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new FollowAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);
        $organization = $this->organizations->findById($organizationId);

        if (! $organization || ! $organization->isPublished()) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        $existing = $this->follows->findByPersonAndOrganization($person->id(), $organizationId);

        if ($existing) {
            $existing->follow();
            $this->follows->save($existing);
        } else {
            $this->follows->save(OrganizationFollow::create($person->id(), $organizationId));
        }

        return new OrganizationFollowStatusResult(
            $organizationId->toString(),
            true,
            $this->follows->countActiveByOrganizationId($organizationId)
        );
    }
}
