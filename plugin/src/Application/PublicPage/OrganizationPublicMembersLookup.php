<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonRepositoryInterface;

/**
 * The members a public Organization page can show, read from メンバー管理
 * itself (the ACTIVE Memberships and their Person) - nothing about a
 * member is copied into the public page's own data, so a name change, a
 * member leaving or being removed shows up on the page as it is.
 *
 * Only what the page may show leaves this class: the Person id (to place
 * the member) and the name. Role, 備考, join date and email are management
 * data (ListOrganizationMembersUseCase) and are never part of it. A member
 * without a name has nothing to show and is left out.
 */
final class OrganizationPublicMembersLookup
{
    private MembershipRepositoryInterface $memberships;
    private PersonRepositoryInterface $people;

    public function __construct(MembershipRepositoryInterface $memberships, PersonRepositoryInterface $people)
    {
        $this->memberships = $memberships;
        $this->people = $people;
    }

    /** @return array<string, array{person_id: string, name: string}> by Person id, in roster order */
    public function forOrganization(string $organizationId): array
    {
        $members = [];

        foreach ($this->memberships->findByOrganizationId(OrganizationId::fromString($organizationId)) as $membership) {
            if (! $membership instanceof Membership || ! $membership->isActive()) {
                continue;
            }

            $person = $this->people->findById($membership->personId());
            $name = $person === null ? '' : trim(implode(' ', array_filter(
                [(string) $person->familyName(), (string) $person->givenName()],
                static fn (string $part): bool => trim($part) !== ''
            )));

            if ($name === '') {
                continue;
            }

            $personId = $membership->personId()->toString();
            $members[$personId] = ['person_id' => $personId, 'name' => $name];
        }

        return $members;
    }
}
