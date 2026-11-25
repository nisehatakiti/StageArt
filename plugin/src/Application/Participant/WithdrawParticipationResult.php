<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

final class WithdrawParticipationResult
{
    public string $participantId;
    public string $productionId;
    public string $status;

    public function __construct(string $participantId, string $productionId, string $status)
    {
        $this->participantId = $participantId;
        $this->productionId = $productionId;
        $this->status = $status;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'participant_id' => $this->participantId,
            'production_id' => $this->productionId,
            'status' => $this->status,
        ];
    }
}
