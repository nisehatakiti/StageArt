<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitation;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;
use Throwable;

/**
 * Mirrors ResolveParticipantInvitationUseCase's own shape: resolves
 * every outstanding OrganizationInvitation for `$email` once `$personId`
 * has just completed their own, ordinary registration. Called
 * internally only - never exposed as a REST endpoint.
 *
 * Unlike the Participant version, this does not need to re-authorize
 * through an existing "create" Use Case - creating a plain MEMBER
 * Membership carries no capability-gated authorization of its own
 * beyond "an OWNER/ORGANIZATION_MANAGER already approved this by
 * creating the invitation in the first place" (already checked once, at
 * CreateOrganizationInvitationUseCase time). A single failure is caught
 * and logged, never allowed to break the registration response this
 * runs inside of, and never allowed to stop other outstanding
 * invitations for the same email from resolving.
 */
final class ResolveOrganizationInvitationUseCase
{
    private OrganizationInvitationRepositoryInterface $invitations;
    private MembershipRepositoryInterface $memberships;

    public function __construct(OrganizationInvitationRepositoryInterface $invitations, MembershipRepositoryInterface $memberships)
    {
        $this->invitations = $invitations;
        $this->memberships = $memberships;
    }

    public function execute(PersonId $personId, string $email): void
    {
        foreach ($this->invitations->findByEmail($email) as $invitation) {
            if (! $invitation->isUsable()) {
                continue;
            }

            try {
                $this->resolveOne($invitation, $personId);
            } catch (Throwable $exception) {
                error_log(sprintf(
                    '[StageArt OrganizationInvitation] failed to resolve invitation=%s for person=%s email=%s: %s',
                    $invitation->id()->toString(),
                    $personId->toString(),
                    $email,
                    $exception->getMessage()
                ));
            }
        }
    }

    private function resolveOne(OrganizationInvitation $invitation, PersonId $personId): void
    {
        $existingMembership = $this->memberships->findByOrganizationAndPerson($invitation->organizationId(), $personId);

        if ($existingMembership === null || ! $existingMembership->isActive()) {
            $membership = Membership::create($invitation->organizationId(), $personId, RoleKey::member());
            $this->memberships->save($membership);
        }

        $invitation->consume();
        $this->invitations->save($invitation);
    }
}
