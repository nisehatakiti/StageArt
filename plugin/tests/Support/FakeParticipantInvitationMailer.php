<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\ParticipantInvitation\ParticipantInvitationMailerInterface;

final class FakeParticipantInvitationMailer implements ParticipantInvitationMailerInterface
{
    /** @var array<int, array{to: string, productionName: string, invitationId: string}> */
    public array $invitationEmails = [];

    /** R14-B stage 4: what the next sends answer (true = accepted by the mail server); throwNext makes the next one throw. */
    public bool $accepts = true;
    public ?\Throwable $throwNext = null;

    public function sendInvitationEmail(string $toEmail, string $productionName, string $invitationId): bool
    {
        if ($this->throwNext !== null) {
            $failure = $this->throwNext;
            $this->throwNext = null;

            throw $failure;
        }

        $this->invitationEmails[] = ['to' => $toEmail, 'productionName' => $productionName, 'invitationId' => $invitationId];

        return $this->accepts;
    }
}
