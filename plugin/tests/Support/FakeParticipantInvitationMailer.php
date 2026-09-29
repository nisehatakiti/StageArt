<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\ParticipantInvitation\ParticipantInvitationMailerInterface;

final class FakeParticipantInvitationMailer implements ParticipantInvitationMailerInterface
{
    /** @var array<int, array{to: string, productionName: string, token: string}> */
    public array $invitationEmails = [];

    public function sendInvitationEmail(string $toEmail, string $productionName, string $token): void
    {
        $this->invitationEmails[] = ['to' => $toEmail, 'productionName' => $productionName, 'token' => $token];
    }
}
