<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\OrganizationInvitation\OrganizationInvitationMailerInterface;

final class FakeOrganizationInvitationMailer implements OrganizationInvitationMailerInterface
{
    /** @var array<int, array{to: string, organizationName: string, token: string}> */
    public array $invitationEmails = [];

    public function sendInvitationEmail(string $toEmail, string $organizationName, string $token): void
    {
        $this->invitationEmails[] = ['to' => $toEmail, 'organizationName' => $organizationName, 'token' => $token];
    }
}
