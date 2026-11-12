<?php

declare(strict_types=1);

namespace StageArt\Domain\OrganizationInvitation;

use StageArt\Domain\Organization\OrganizationId;

interface OrganizationInvitationRepositoryInterface
{
    public function save(OrganizationInvitation $invitation): void;

    public function findById(OrganizationInvitationId $id): ?OrganizationInvitation;

    public function findByTokenHash(string $tokenHash): ?OrganizationInvitation;

    /**
     * All invitations (any status) for a given Organization/email pair -
     * callers filter for isUsable() themselves (see
     * CreateOrganizationInvitationUseCase's duplicate-invitation check).
     *
     * @return OrganizationInvitation[]
     */
    public function findByOrganizationAndEmail(OrganizationId $organizationId, string $email): array;

    /**
     * Every invitation (any Organization, any status) for a given email -
     * used by ResolveOrganizationInvitationUseCase to resolve every
     * outstanding invitation a freshly-registered email might satisfy.
     *
     * @return OrganizationInvitation[]
     */
    public function findByEmail(string $email): array;

    /**
     * @return OrganizationInvitation[]
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;
}
