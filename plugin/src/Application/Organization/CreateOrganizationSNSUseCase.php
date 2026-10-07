<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use InvalidArgumentException;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationSNS;
use StageArt\Domain\Organization\OrganizationSNSRepositoryInterface;
use StageArt\Domain\Organization\OrganizationSNSService;
use StageArt\Domain\Role\RoleKey;

/**
 * docs/04-DomainModel/OrganizationSNS.md "Administration": "作成・変更・
 *削除は、Organizationの公開情報を管理できるPermissionを持つPersonに限定す
 * る。MembershipだけではOrganizationSNSの編集権限を自動付与しない。" -
 * scoped to OWNER/ORGANIZATION_MANAGER, the same pair every other
 * Organization-info-editing action in this round uses (plain MEMBER is
 * excluded, matching the doc's explicit "Membershipだけでは...付与しない").
 */
final class CreateOrganizationSNSUseCase
{
    private OrganizationSNSRepositoryInterface $sns;
    private OrganizationAuthorizationService $authorization;

    public function __construct(OrganizationSNSRepositoryInterface $sns, OrganizationAuthorizationService $authorization)
    {
        $this->sns = $sns;
        $this->authorization = $authorization;
    }

    public function execute(CreateOrganizationSNSCommand $command): OrganizationSNSResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);
        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $person || ! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can manage SNS links.');
        }

        $accountIdentifier = ltrim(trim($command->accountIdentifier), '@');
        if ($accountIdentifier === '') {
            throw new InvalidArgumentException('account_identifier must not be empty.');
        }

        $profileUrl = $this->profileUrlForService(OrganizationSNSService::fromString($command->service), $accountIdentifier, $command->profileUrl);

        $sns = OrganizationSNS::create(
            $organizationId,
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

    /**
     * X/Instagram/Facebook: profile_url is always derived from the
     * sanitized account identifier - whatever the client sent for
     * profile_url is ignored outright, not merely unvalidated, so a
     * garbage value there can never reach storage. OTHER (not
     * creatable from the current UI, but still a valid existing
     * OrganizationSNSService - see that class's own docblock) is the
     * only case the client's own profile_url is actually used, so it
     * still gets real URL validation here rather than being accepted
     * verbatim.
     */
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
