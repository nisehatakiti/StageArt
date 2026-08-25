<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

final class ListMyMembershipsUseCase
{
    private \StageArt\Domain\Membership\MembershipRepositoryInterface $memberships;
    private OrganizationRepositoryInterface $organizations;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        \StageArt\Domain\Membership\MembershipRepositoryInterface $memberships,
        OrganizationRepositoryInterface $organizations,
        OrganizationAuthorizationService $authorization
    ) {
        $this->memberships = $memberships;
        $this->organizations = $organizations;
        $this->authorization = $authorization;
    }

    /**
     * @return MyMembershipResult[]
     */
    public function execute(ListMyMembershipsQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new MembershipAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $memberships = $this->memberships->findByPersonId($person->id());

        if ($memberships === []) {
            return [];
        }

        $organizations = $this->organizations->findByIds(array_map(
            static fn (Membership $membership) => $membership->organizationId(),
            $memberships
        ));

        $organizationsById = [];
        foreach ($organizations as $organization) {
            $organizationsById[$organization->id()->toString()] = $organization;
        }

        $results = [];
        foreach ($memberships as $membership) {
            $organization = $organizationsById[$membership->organizationId()->toString()] ?? null;

            if (! $organization) {
                continue;
            }

            $results[] = MyMembershipResult::fromDomain($membership, $organization);
        }

        return $results;
    }
}
