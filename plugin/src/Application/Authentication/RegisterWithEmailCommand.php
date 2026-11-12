<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * StageArt 招待登録のメール確認省略ラウンド: `invitationToken` is the raw
 * ParticipantInvitation token from an invitation link's own
 * `/register?token=...` query param - optional, null for ordinary
 * self-registration. When present, RegisterWithEmailUseCase treats the
 * invitation's own recorded email as authoritative (never the `email`
 * field here - see that Use Case's own docblock) and skips the normal
 * email-confirmation step, since the invitation link itself already
 * proves the address is reachable.
 */
final class RegisterWithEmailCommand
{
    public string $email;
    public string $password;
    public ?string $invitationToken;

    public function __construct(string $email, string $password, ?string $invitationToken = null)
    {
        $this->email = $email;
        $this->password = $password;
        $this->invitationToken = $invitationToken;
    }
}
