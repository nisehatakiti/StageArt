<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use InvalidArgumentException;
use StageArt\Domain\Organization\OrganizationExternalLink;
use StageArt\Domain\Organization\OrganizationExternalLinkRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Role\RoleKey;

final class CreateOrganizationExternalLinkUseCase
{
    private OrganizationExternalLinkRepositoryInterface $links;
    private OrganizationAuthorizationService $authorization;

    public function __construct(OrganizationExternalLinkRepositoryInterface $links, OrganizationAuthorizationService $authorization)
    {
        $this->links = $links;
        $this->authorization = $authorization;
    }

    public function execute(CreateOrganizationExternalLinkCommand $command): OrganizationExternalLinkResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);
        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $person || ! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can manage external links.');
        }

        $label = trim($command->label);
        $url = trim($command->url);

        if ($label === '') {
            throw new InvalidArgumentException('label must not be empty.');
        }

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Invalid url: {$command->url}");
        }

        $link = OrganizationExternalLink::create($organizationId, $label, $url, $command->displayOrder);
        $this->links->save($link);

        return OrganizationExternalLinkResult::fromDomain($link);
    }
}
