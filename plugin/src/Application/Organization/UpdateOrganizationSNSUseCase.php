<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use InvalidArgumentException;
use StageArt\Domain\Organization\OrganizationSNSId;
use StageArt\Domain\Organization\OrganizationSNSRepositoryInterface;
use StageArt\Domain\Organization\OrganizationSNSService;
use StageArt\Domain\Role\RoleKey;

final class UpdateOrganizationSNSUseCase
{
    private OrganizationSNSRepositoryInterface $sns;
    private OrganizationAuthorizationService $authorization;

    public function __construct(OrganizationSNSRepositoryInterface $sns, OrganizationAuthorizationService $authorization)
    {
        $this->sns = $sns;
        $this->authorization = $authorization;
    }

    public function execute(UpdateOrganizationSNSCommand $command): OrganizationSNSResult
    {
        $sns = $this->sns->findById(OrganizationSNSId::fromString($command->id));

        if (! $sns) {
            throw new OrganizationSNSNotFoundException($command->id);
        }

        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person || ! $this->authorization->hasRole($person, $sns->organizationId(), [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can manage SNS links.');
        }

        $accountIdentifier = trim($command->accountIdentifier);
        $profileUrl = trim($command->profileUrl);

        if ($accountIdentifier === '') {
            throw new InvalidArgumentException('account_identifier must not be empty.');
        }

        if ($profileUrl === '' || filter_var($profileUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Invalid profile_url: {$command->profileUrl}");
        }

        $sns->update(
            OrganizationSNSService::fromString($command->service),
            $accountIdentifier,
            $profileUrl,
            $command->displayName,
            $command->displayOrder,
            $command->visibility
        );

        $this->sns->save($sns);

        return OrganizationSNSResult::fromDomain($sns);
    }
}
