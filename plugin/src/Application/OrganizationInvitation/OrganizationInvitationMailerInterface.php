<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

/** Mirrors ParticipantInvitationMailerInterface's own shape - the
 * recipient has no StageArt account yet, so this is a separate Port
 * from AuthMailerInterface. */
interface OrganizationInvitationMailerInterface
{
    public function sendInvitationEmail(string $toEmail, string $organizationName, string $token): void;
}
