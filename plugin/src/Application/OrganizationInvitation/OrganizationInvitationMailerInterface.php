<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

/** Mirrors ParticipantInvitationMailerInterface's own shape - the
 * recipient has no StageArt account yet, so this is a separate Port
 * from AuthMailerInterface. */
interface OrganizationInvitationMailerInterface
{
    /**
     * @param string $token        the invitation token (not put in the mail since R14-B stage 10; kept for the unchanged invitation itself)
     * @param string $invitationId the invitation the mail's link opens (/organization-invitations/{id})
     */
    public function sendInvitationEmail(string $toEmail, string $organizationName, string $token, string $invitationId): void;
}
