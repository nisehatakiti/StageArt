<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;

final class RehearsalAttendanceResult
{
    public string $id;
    public string $rehearsalId;
    public string $personId;
    public string $phase;
    public string $status;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $rehearsalId,
        string $personId,
        string $phase,
        string $status,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->rehearsalId = $rehearsalId;
        $this->personId = $personId;
        $this->phase = $phase;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(RehearsalAttendance $attendance): self
    {
        return new self(
            $attendance->id()->toString(),
            $attendance->rehearsalId()->toString(),
            $attendance->personId()->toString(),
            $attendance->phase()->toString(),
            $attendance->status()->toString(),
            $attendance->createdAt()->format(DATE_ATOM),
            $attendance->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rehearsal_id' => $this->rehearsalId,
            'person_id' => $this->personId,
            'phase' => $this->phase,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
