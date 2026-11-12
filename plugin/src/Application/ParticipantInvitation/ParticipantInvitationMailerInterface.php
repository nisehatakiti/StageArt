<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/**
 * A separate Mailer Port from AuthMailerInterface, mirroring
 * QuestionnaireMailerInterface's own precedent (its docblock: "mirrors
 * AuthMailerInterface's own shape... rather than going through
 * NotificationContract") - AuthMailerInterface's own docblock scopes it
 * explicitly to "the account owner's email address" for password-reset/
 * email-verification, which does not describe this message (its
 * recipient has no StageArt account yet at all).
 */
interface ParticipantInvitationMailerInterface
{
    public function sendInvitationEmail(string $toEmail, string $productionName, string $token): void;
}
