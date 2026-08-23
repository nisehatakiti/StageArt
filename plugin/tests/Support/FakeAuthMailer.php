<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Authentication\AuthMailerInterface;

final class FakeAuthMailer implements AuthMailerInterface
{
    /** @var array<int, array{to: string, token: string}> */
    public array $passwordResetEmails = [];

    /** @var array<int, array{to: string, token: string}> */
    public array $verificationEmails = [];

    public function sendPasswordResetEmail(string $toEmail, string $token): void
    {
        $this->passwordResetEmails[] = ['to' => $toEmail, 'token' => $token];
    }

    public function sendEmailVerificationEmail(string $toEmail, string $token): void
    {
        $this->verificationEmails[] = ['to' => $toEmail, 'token' => $token];
    }
}
