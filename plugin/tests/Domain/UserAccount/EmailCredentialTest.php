<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\UserAccount;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccountId;

final class EmailCredentialTest extends TestCase
{
    public function test_create_stores_email_and_hash_and_starts_unverified(): void
    {
        $credential = EmailCredential::create(UserAccountId::generate(), 'owner@example.com', 'hashed-value');

        $this->assertSame('owner@example.com', $credential->email());
        $this->assertSame('hashed-value', $credential->passwordHash());
        $this->assertFalse($credential->isEmailVerified());
        $this->assertNull($credential->emailVerifiedAt());
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmailCredential::create(UserAccountId::generate(), 'not-an-email', 'hashed-value');
    }

    public function test_empty_password_hash_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmailCredential::create(UserAccountId::generate(), 'owner@example.com', '   ');
    }

    public function test_mark_email_verified(): void
    {
        $credential = EmailCredential::create(UserAccountId::generate(), 'owner@example.com', 'hashed-value');

        $credential->markEmailVerified();

        $this->assertTrue($credential->isEmailVerified());
        $this->assertNotNull($credential->emailVerifiedAt());
    }

    public function test_change_password_hash(): void
    {
        $credential = EmailCredential::create(UserAccountId::generate(), 'owner@example.com', 'old-hash');

        $credential->changePasswordHash('new-hash');

        $this->assertSame('new-hash', $credential->passwordHash());
    }
}
