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

/** JoinKey.md's "Issuer": the Organization Owner, or a delegate holding
 * メンバー管理 (DelegateArea::MEMBERS), may issue an Organization Join Key. */
final class IssueOrganizationJoinKeyUseCase
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

    public function execute(IssueOrganizationJoinKeyCommand $command): JoinKeyResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->authorization->hasDelegateArea($person, $organizationId, DelegateArea::MEMBERS)) {
            throw new OrganizationAccessDeniedException('Only the Organization Owner or a delegate with member management can issue a Join Key.');
        }

        if (! $this->organizations->findById($organizationId)) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        $joinKey = JoinKey::issueForOrganization($organizationId->toString(), $person->id());
        $this->joinKeys->save($joinKey);

        return JoinKeyResult::fromDomain($joinKey);
    }
}
