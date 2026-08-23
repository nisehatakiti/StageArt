<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Authentication;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Authentication\RefreshToken;
use StageArt\Domain\UserAccount\UserAccountId;

final class RefreshTokenTest extends TestCase
{
    public function test_a_freshly_created_token_is_usable(): void
    {
        $token = RefreshToken::create(
            UserAccountId::generate(),
            'hash',
            (new DateTimeImmutable())->add(new DateInterval('P30D'))
        );

        $this->assertTrue($token->isUsable());
        $this->assertFalse($token->isRevoked());
        $this->assertFalse($token->isExpired());
    }

    public function test_an_expired_token_is_not_usable(): void
    {
        $token = RefreshToken::create(
            UserAccountId::generate(),
            'hash',
            (new DateTimeImmutable())->sub(new DateInterval('P1D'))
        );

        $this->assertTrue($token->isExpired());
        $this->assertFalse($token->isUsable());
    }

    public function test_revoke_makes_a_token_unusable_even_if_not_yet_expired(): void
    {
        $token = RefreshToken::create(
            UserAccountId::generate(),
            'hash',
            (new DateTimeImmutable())->add(new DateInterval('P30D'))
        );

        $token->revoke();

        $this->assertTrue($token->isRevoked());
        $this->assertFalse($token->isUsable());
    }

    public function test_revoke_is_idempotent_keeping_the_first_revocation_time(): void
    {
        $token = RefreshToken::create(
            UserAccountId::generate(),
            'hash',
            (new DateTimeImmutable())->add(new DateInterval('P30D'))
        );

        $token->revoke();
        $firstRevokedAt = $token->revokedAt();

        $token->revoke();

        $this->assertSame($firstRevokedAt, $token->revokedAt());
    }
}
