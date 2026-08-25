<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Person\Person;

final class MembershipRequestResult
{
    public string $id;
    public string $organizationId;
    public string $personId;
    public ?string $personFamilyName;
    public ?string $personGivenName;
    public string $status;
    public string $requestedAt;
    public ?string $joinedAt;

    public function __construct(
        string $id,
        string $organizationId,
        string $personId,
        ?string $personFamilyName,
        ?string $personGivenName,
        string $status,
        string $requestedAt,
        ?string $joinedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->personId = $personId;
        $this->personFamilyName = $personFamilyName;
        $this->personGivenName = $personGivenName;
        $this->status = $status;
        $this->requestedAt = $requestedAt;
        $this->joinedAt = $joinedAt;
    }

    public static function fromDomain(Membership $membership, ?Person $person): self
    {
        return new self(
            $membership->id()->toString(),
            $membership->organizationId()->toString(),
            $membership->personId()->toString(),
            $person?->familyName(),
            $person?->givenName(),
            $membership->status(),
            $membership->createdAt()->format(DATE_ATOM),
            $membership->joinedAt()?->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'person_id' => $this->personId,
            'person_family_name' => $this->personFamilyName,
            'person_given_name' => $this->personGivenName,
            'status' => $this->status,
            'requested_at' => $this->requestedAt,
            'joined_at' => $this->joinedAt,
        ];
    }
}
