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

        $accountIdentifier = ltrim(trim($command->accountIdentifier), '@');
        if ($accountIdentifier === '') {
            throw new InvalidArgumentException('account_identifier must not be empty.');
        }

        $profileUrl = $this->profileUrlForService(OrganizationSNSService::fromString($command->service), $accountIdentifier, $command->profileUrl);

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

    /** Mirrors CreateOrganizationSNSUseCase::profileUrlForService() - see
     * that method's own docblock. */
    private function profileUrlForService(OrganizationSNSService $service, string $accountIdentifier, ?string $providedUrl): ?string
    {
        if (in_array($service->toString(), [OrganizationSNSService::X, OrganizationSNSService::INSTAGRAM, OrganizationSNSService::FACEBOOK], true)) {
            return match ($service->toString()) {
                OrganizationSNSService::X => 'https://x.com/' . rawurlencode($accountIdentifier),
                OrganizationSNSService::INSTAGRAM => 'https://www.instagram.com/' . rawurlencode($accountIdentifier),
                default => 'https://www.facebook.com/' . rawurlencode($accountIdentifier),
            };
        }

        $trimmed = $providedUrl !== null ? trim($providedUrl) : '';

        if ($trimmed === '') {
            return null;
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Invalid profile_url: {$providedUrl}");
        }

        return $trimmed;
    }
}
