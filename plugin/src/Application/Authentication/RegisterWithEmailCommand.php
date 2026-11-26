<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * StageArt 招待登録のメール確認省略ラウンド: `invitationToken` is the raw
 * token from an OrganizationInvitation link's own `/register?token=...`
 * query param - optional, null for ordinary self-registration. When it is a
 * usable OrganizationInvitation's, RegisterWithEmailUseCase treats that
 * invitation's own recorded email as authoritative and skips the normal
 * email-confirmation step. PR #7 R14-B stage 4: a Production (participant)
 * invitation token is ignored - see RegisterWithEmailUseCase.
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
