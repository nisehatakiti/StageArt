<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;

/**
 * StageArt Organization管理UI/UX統一 "メンバー管理": the roster's 備考
 * column, editable after the fact - mirrors Production's メンバー管理
 * screen, where role/備考 edits on existing rows are applied via their
 * own narrow update call. Only 備考 is editable here; Role changes stay
 * exclusively behind 代理人を設定/代表者交代 (see those UseCases' own
 * docblocks for why this screen never lets Role be edited directly).
 */
final class UpdateOrganizationMemberRemarksUseCase
{
    private MembershipRepositoryInterface $memberships;
    private OrganizationAuthorizationService $authorization;

    public function __construct(MembershipRepositoryInterface $memberships, OrganizationAuthorizationService $authorization)
    {
        $this->memberships = $memberships;
        $this->authorization = $authorization;
    }

    public function execute(UpdateOrganizationMemberRemarksCommand $command): OrganizationMemberResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);
        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $person || ! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can update a member.');
        }

        $targetPersonId = PersonId::fromString($command->personId);
        $membership = $this->memberships->findByOrganizationAndPerson($organizationId, $targetPersonId);

        if (! $membership || ! $membership->isActive()) {
            throw new OrganizationMemberNotFoundException($command->personId);
        }

        $membership->changeRemarks($command->remarks);
        $this->memberships->save($membership);

        return OrganizationMemberResult::fromDomain($membership, null);
    }
}
