<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Person\Person;

/**
 * StageArt メンバー管理 instruction (担当者権限をメンバー管理へ統合・整理 §1):
 * `personFamilyName`/`personGivenName` are resolved the same way
 * ProductionDelegateResult already resolves its own target Person's name
 * (both nullable, matching Person::familyName()/givenName()'s own
 * nullability - a Person who has not completed set-name.tsx yet). Only
 * meaningful when subjectType is PERSON; null for NAME_ONLY/ORGANIZATION
 * subjects (NAME_ONLY already has its own displayName field instead).
 */
final class ParticipantResult
{
    public string $id;
    public string $productionId;
    public string $subjectType;
    public string $subjectId;
    public string $participantType;
    public string $status;
    public string $createdAt;
    public string $updatedAt;
    public ?string $remarks;
    public ?string $displayName;
    public ?string $personFamilyName;
    public ?string $personGivenName;

    private function __construct(
        string $id,
        string $productionId,
        string $subjectType,
        string $subjectId,
        string $participantType,
        string $status,
        string $createdAt,
        string $updatedAt,
        ?string $remarks,
        ?string $displayName,
        ?string $personFamilyName,
        ?string $personGivenName
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->participantType = $participantType;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->remarks = $remarks;
        $this->displayName = $displayName;
        $this->personFamilyName = $personFamilyName;
        $this->personGivenName = $personGivenName;
    }

    public static function fromDomain(Participant $participant, ?Person $person = null): self
    {
        return new self(
            $participant->id()->toString(),
            $participant->productionId()->toString(),
            $participant->subjectType()->toString(),
            $participant->subjectId(),
            $participant->participantType()->toString(),
            $participant->status()->toString(),
            $participant->createdAt()->format(DATE_ATOM),
            $participant->updatedAt()->format(DATE_ATOM),
            $participant->remarks(),
            $participant->displayName(),
            $person?->familyName(),
            $person?->givenName()
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
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'participant_type' => $this->participantType,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'remarks' => $this->remarks,
            'display_name' => $this->displayName,
            'person_family_name' => $this->personFamilyName,
            'person_given_name' => $this->personGivenName,
        ];
    }
}
