<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use InvalidArgumentException;
use StageArt\Application\JoinKey\JoinKeyNotFoundException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

/**
 * docs/03-InitialOnboardingAndJoinKey.md's two converging entry points
 * ("団体を探す" / "参加コードを持っている") both create the same REQUESTED
 * Membership - see Membership.md's "Membership Request". A search-based
 * request requires the target Organization to actually be published
 * (discoverable in the first place); a Join Key-based request does not,
 * since the key itself is the invitation and was handed out directly by
 * the Owner regardless of the Organization's public visibility.
 */
final class RequestOrganizationMembershipUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private MembershipRepositoryInterface $memberships;
    private JoinKeyRepositoryInterface $joinKeys;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        MembershipRepositoryInterface $memberships,
        JoinKeyRepositoryInterface $joinKeys,
        OrganizationAuthorizationService $authorization
    ) {
        $this->organizations = $organizations;
        $this->memberships = $memberships;
        $this->joinKeys = $joinKeys;
        $this->authorization = $authorization;
    }

    public function execute(RequestOrganizationMembershipCommand $command): MembershipRequestResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new MembershipAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $joinKey = null;
        $organizationId = null;

        if ($command->joinKeyCode !== null) {
            $joinKey = $this->joinKeys->findByCode(JoinKey::normalizeCode($command->joinKeyCode));

            if (! $joinKey || ! $joinKey->isUsable() || $joinKey->targetType() !== JoinKey::TARGET_TYPE_ORGANIZATION) {
                throw new JoinKeyNotFoundException($command->joinKeyCode);
            }

            $organizationId = OrganizationId::fromString($joinKey->targetId());
            $organization = $this->organizations->findById($organizationId);

            if (! $organization) {
                throw new OrganizationNotFoundException($joinKey->targetId());
            }
        } elseif ($command->organizationId !== null) {
            $organizationId = OrganizationId::fromString($command->organizationId);
            $organization = $this->organizations->findById($organizationId);

            if (! $organization || ! $organization->isPublished()) {
                throw new OrganizationNotFoundException($command->organizationId);
            }
        } else {
            throw new InvalidArgumentException('Either organizationId or joinKeyCode is required.');
        }

        $existing = $this->memberships->findByOrganizationAndPerson($organizationId, $person->id());

        if ($existing && ($existing->isActive() || $existing->isPending())) {
            throw new MembershipAlreadyExistsException(
                "Person {$person->id()->toString()} already has a {$existing->status()} Membership for Organization {$organizationId->toString()}."
            );
        }

        $membership = Membership::requestMembership($organizationId, $person->id());
        $this->memberships->save($membership);

        if ($joinKey !== null) {
            $joinKey->recordUse();
            $this->joinKeys->save($joinKey);
        }

        return MembershipRequestResult::fromDomain($membership, $person);
    }
}
