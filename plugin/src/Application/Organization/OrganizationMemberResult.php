<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Person\Person;

/**
 * StageArt 団体管理UI整理: the Organization "メンバー管理" roster - every
 * ACTIVE Membership, with the Person's own display name. Deliberately
 * excludes REQUESTED/REJECTED Memberships (those are the separate
 * Membership Request approval queue, see MembershipRequestResult).
 */
final class OrganizationMemberResult
{
    public string $membershipId;
    public string $personId;
    public ?string $personFamilyName;
    public ?string $personGivenName;
    public ?string $email;
    public string $roleKey;
    public ?string $joinedAt;
    public ?string $remarks;

    private function __construct(
        string $membershipId,
        string $personId,
        ?string $personFamilyName,
        ?string $personGivenName,
        ?string $email,
        string $roleKey,
        ?string $joinedAt,
        ?string $remarks
    ) {
        $this->membershipId = $membershipId;
        $this->personId = $personId;
        $this->personFamilyName = $personFamilyName;
        $this->personGivenName = $personGivenName;
        $this->email = $email;
        $this->roleKey = $roleKey;
        $this->joinedAt = $joinedAt;
        $this->remarks = $remarks;
    }

    public static function fromDomain(Membership $membership, ?Person $person, ?string $email = null): self
    {
        return new self(
            $membership->id()->toString(),
            $membership->personId()->toString(),
            $person?->familyName(),
            $person?->givenName(),
            $email,
            $membership->roleKey()->toString(),
            $membership->joinedAt()?->format(DATE_ATOM),
            $membership->remarks()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'membership_id' => $this->membershipId,
            'person_id' => $this->personId,
            'person_family_name' => $this->personFamilyName,
            'person_given_name' => $this->personGivenName,
            'email' => $this->email,
            'role_key' => $this->roleKey,
            'joined_at' => $this->joinedAt,
            'remarks' => $this->remarks,
        ];
    }
}
