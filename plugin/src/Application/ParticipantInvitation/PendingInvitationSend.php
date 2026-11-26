<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B §11.3 (stage 4): a send attempt accepted (and committed) by the
 * create / resend API, to be delivered after the commit by
 * InvitationSendService::deliver(). Held in memory only.
 */
final class PendingInvitationSend
{
    public string $sendId;
    public ParticipantInvitationId $invitationId;
    public ProductionId $productionId;
    public string $email;
    public string $productionName;

    public function __construct(string $sendId, ParticipantInvitationId $invitationId, ProductionId $productionId, string $email, string $productionName)
    {
        $this->sendId = $sendId;
        $this->invitationId = $invitationId;
        $this->productionId = $productionId;
        $this->email = $email;
        $this->productionName = $productionName;
    }
}
