<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;
use StageArt\Domain\Membership\DelegateArea;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

/** Listing an Organization's Join Keys reveals codes that work for
 * joining, so it needs the same gate as issuing one: the Owner, or a
 * delegate holding メンバー管理 (DelegateArea::MEMBERS). */
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

        if (! $this->authorization->hasDelegateArea($person, $organizationId, DelegateArea::MEMBERS)) {
            throw new OrganizationAccessDeniedException('Only the Organization Owner or a delegate with member management can view Join Keys.');
        }

        if (! $this->organizations->findById($organizationId)) {
            throw new OrganizationNotFoundException($query->organizationId);
        }

        return array_map(
            static fn (JoinKey $joinKey) => JoinKeyResult::fromDomain($joinKey),
            $this->joinKeys->findByTarget(JoinKey::TARGET_TYPE_ORGANIZATION, $organizationId->toString())
        );
    }
}
