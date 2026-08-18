<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\UserAccount;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\UserAccount\ExternalIdentity;
use StageArt\Domain\UserAccount\UserAccountId;

final class ExternalIdentityTest extends TestCase
{
    public function test_create_stores_provider_and_provider_user_id(): void
    {
        $identity = ExternalIdentity::create(UserAccountId::generate(), 'google', 'google-sub-12345');

        $this->assertSame('google', $identity->provider());
        $this->assertSame('google-sub-12345', $identity->providerUserId());
    }

    public function test_empty_provider_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ExternalIdentity::create(UserAccountId::generate(), '', 'google-sub-12345');
    }

    public function test_empty_provider_user_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ExternalIdentity::create(UserAccountId::generate(), 'google', '');
    }
}
