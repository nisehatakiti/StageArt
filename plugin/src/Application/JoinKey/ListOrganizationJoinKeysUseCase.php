<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/** Listing an Organization's Join Keys reveals codes that work for
 * joining, so it needs the same gate as issuing/disabling one: an
 * Organization Owner (IssueOrganizationJoinKeyUseCase). As for Production
 * Join Keys, an unknown Organization is answered 404 and an existing one
 * the caller may not manage 403 (existence first, then the Role). Only the
 * authorized Organization's keys are read, every status included. */
final class ListOrganizationJoinKeysUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private JoinKeyRepositoryInterface $joinKeys;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        JoinKeyRepositoryInterface $joinKeys,
        OrganizationAuthorizationService $authorization
    ) {
        $this->organizations = $organizations;
        $this->joinKeys = $joinKeys;
        $this->authorization = $authorization;
    }

    /**
     * @return JoinKeyResult[]
     */
    public function execute(ListOrganizationJoinKeysQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($query->organizationId);

        if (! $this->organizations->findById($organizationId)) {
            throw new OrganizationNotFoundException($query->organizationId);
        }

        if (! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner can view Join Keys.');
        }

        return array_map(
            static fn (JoinKey $joinKey) => JoinKeyResult::fromDomain($joinKey),
            $this->joinKeys->findByTarget(JoinKey::TARGET_TYPE_ORGANIZATION, $organizationId->toString())
        );
    }
}
