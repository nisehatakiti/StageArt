<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Domain\Participant\Participant;

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

    private function __construct(
        string $id,
        string $productionId,
        string $subjectType,
        string $subjectId,
        string $participantType,
        string $status,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->participantType = $participantType;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(Participant $participant): self
    {
        return new self(
            $participant->id()->toString(),
            $participant->productionId()->toString(),
            $participant->subjectType()->toString(),
            $participant->subjectId(),
            $participant->participantType()->toString(),
            $participant->status()->toString(),
            $participant->createdAt()->format(DATE_ATOM),
            $participant->updatedAt()->format(DATE_ATOM)
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
        ];
    }
}
