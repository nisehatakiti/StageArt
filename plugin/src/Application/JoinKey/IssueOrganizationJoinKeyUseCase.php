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

/** JoinKey.md's "Issuer": only an Organization Owner may issue an
 * Organization Join Key. */
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

        if (! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner can issue a Join Key.');
        }

        if (! $this->organizations->findById($organizationId)) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        $joinKey = JoinKey::issueForOrganization($organizationId->toString(), $person->id());
        $this->joinKeys->save($joinKey);

        return JoinKeyResult::fromDomain($joinKey);
    }
}
