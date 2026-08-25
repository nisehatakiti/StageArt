<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Person\Person;

final class ParticipantRequestResult
{
    public string $id;
    public string $productionId;
    public string $personId;
    public ?string $personFamilyName;
    public ?string $personGivenName;
    public string $participantType;
    public string $status;
    public string $requestedAt;

    public function __construct(
        string $id,
        string $productionId,
        string $personId,
        ?string $personFamilyName,
        ?string $personGivenName,
        string $participantType,
        string $status,
        string $requestedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->personId = $personId;
        $this->personFamilyName = $personFamilyName;
        $this->personGivenName = $personGivenName;
        $this->participantType = $participantType;
        $this->status = $status;
        $this->requestedAt = $requestedAt;
    }

    public static function fromDomain(Participant $participant, ?Person $person): self
    {
        return new self(
            $participant->id()->toString(),
            $participant->productionId()->toString(),
            $participant->subjectId(),
            $person?->familyName(),
            $person?->givenName(),
            $participant->participantType()->toString(),
            $participant->status()->toString(),
            $participant->createdAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'production_id' => $this->productionId,
            'person_id' => $this->personId,
            'person_family_name' => $this->personFamilyName,
            'person_given_name' => $this->personGivenName,
            'participant_type' => $this->participantType,
            'status' => $this->status,
            'requested_at' => $this->requestedAt,
        ];
    }
}
