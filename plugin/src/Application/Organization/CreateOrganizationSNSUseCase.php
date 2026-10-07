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
}


    private function profileUrlForService(OrganizationSNSService $service, string $accountIdentifier, ?string $providedUrl): ?string
    {
        return match ($service->toString()) {
            OrganizationSNSService::X => 'https://x.com/' . rawurlencode($accountIdentifier),
            OrganizationSNSService::INSTAGRAM => 'https://www.instagram.com/' . rawurlencode($accountIdentifier),
            OrganizationSNSService::FACEBOOK => 'https://www.facebook.com/' . rawurlencode($accountIdentifier),
            default => $providedUrl !== null && trim($providedUrl) !== '' ? trim($providedUrl) : null,
        };
    }
