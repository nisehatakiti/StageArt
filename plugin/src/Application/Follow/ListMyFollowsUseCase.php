<?php

declare(strict_types=1);

namespace StageArt\Application\Follow;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Follow\OrganizationFollowRepositoryInterface;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

final class ListMyFollowsUseCase
{
    private OrganizationFollowRepositoryInterface $follows;
    private OrganizationRepositoryInterface $organizations;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        OrganizationFollowRepositoryInterface $follows,
        OrganizationRepositoryInterface $organizations,
        OrganizationAuthorizationService $authorization
    ) {
        $this->follows = $follows;
        $this->organizations = $organizations;
        $this->authorization = $authorization;
    }

    /**
     * @return MyFollowResult[]
     */
    public function execute(ListMyFollowsQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new FollowAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $follows = $this->follows->findActiveByPersonId($person->id());

        if ($follows === []) {
            return [];
        }

        $organizations = $this->organizations->findByIds(array_map(
            static fn ($follow) => $follow->organizationId(),
            $follows
        ));

        /** @var array<string, \StageArt\Domain\Organization\Organization> $organizationsById */
        $organizationsById = [];
        foreach ($organizations as $organization) {
            $organizationsById[$organization->id()->toString()] = $organization;
        }

        $results = [];
        foreach ($follows as $follow) {
            $organization = $organizationsById[$follow->organizationId()->toString()] ?? null;

            // Defensive: an Organization could in principle be deleted
            // out from under an existing Follow row. Skip rather than
            // error - a Person's Follow list simply omits it.
            if (! $organization) {
                continue;
            }

            $results[] = MyFollowResult::fromDomain($follow, $organization);
        }

        return $results;
    }
}
