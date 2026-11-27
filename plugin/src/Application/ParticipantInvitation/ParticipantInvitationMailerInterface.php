<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/**
 * A separate Mailer Port from AuthMailerInterface, mirroring
 * QuestionnaireMailerInterface's own precedent (its docblock: "mirrors
 * AuthMailerInterface's own shape... rather than going through
 * NotificationContract") - AuthMailerInterface's own docblock scopes it
 * explicitly to "the account owner's email address" for password-reset/
 * email-verification, which does not describe this message.
 *
 * PR #7 R14-B stage 4 (design doc §4.1 / §9.2 / §10, U5 C案): the
 * invitation e-mail carries no token. Every invitation e-mail is the same
 * text with the same link to `/participant-invitations` (the invitee's own
 * list, login required), whether or not the address belongs to an account.
 * Returns whether the mail server accepted it (wp_mail() true) - not
 * delivery; InvitationSendService records it as SENT / FAILED.
 */
interface ParticipantInvitationMailerInterface
{
    /** @param string $invitationId the invitation the mail's link opens (/participant-invitations/{id}) */
    public function sendInvitationEmail(string $toEmail, string $productionName, string $invitationId): bool;
}
