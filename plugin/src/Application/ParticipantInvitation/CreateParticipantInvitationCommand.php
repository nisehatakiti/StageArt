<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

final class CreateParticipantInvitationCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $email;
    public string $participantType;
    public ?string $remarks;

    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        string $email,
        string $participantType,
        ?string $remarks = null
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->email = $email;
        $this->participantType = $participantType;
        $this->remarks = $remarks;
    }
}
