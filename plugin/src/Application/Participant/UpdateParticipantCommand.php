<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

final class UpdateParticipantCommand
{
    public string $participantId;
    public int $requestedByWordPressUserId;
    public string $participantType;
    public string $status;
    public ?string $remarks;

    public function __construct(
        string $participantId,
        int $requestedByWordPressUserId,
        string $participantType,
        string $status,
        ?string $remarks = null
    ) {
        $this->participantId = $participantId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->participantType = $participantType;
        $this->status = $status;
        $this->remarks = $remarks;
    }
}
