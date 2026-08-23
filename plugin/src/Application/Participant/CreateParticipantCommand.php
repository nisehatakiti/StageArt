<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

final class CreateParticipantCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $subjectType;
    public string $subjectId;
    public string $participantType;

    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        string $subjectType,
        string $subjectId,
        string $participantType
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->participantType = $participantType;
    }
}
