<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Authentication;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Authentication\PasswordResetToken;
use StageArt\Domain\UserAccount\UserAccountId;

final class PasswordResetTokenTest extends TestCase
{
    public function test_a_freshly_created_token_is_usable(): void
    {
        $token = PasswordResetToken::create(
            UserAccountId::generate(),
            'hash',
            (new DateTimeImmutable())->add(new DateInterval('PT1H'))
        );

        $this->assertTrue($token->isUsable());
        $this->assertFalse($token->isConsumed());
        $this->assertFalse($token->isExpired());
    }

    public function test_an_expired_token_is_not_usable(): void
    {
        $token = PasswordResetToken::create(
            UserAccountId::generate(),
            'hash',
            (new DateTimeImmutable())->sub(new DateInterval('PT1H'))
        );

        $this->assertTrue($token->isExpired());
        $this->assertFalse($token->isUsable());
    }

    public function test_consume_makes_a_token_unusable_even_if_not_yet_expired(): void
    {
        $token = PasswordResetToken::create(
            UserAccountId::generate(),
            'hash',
            (new DateTimeImmutable())->add(new DateInterval('PT1H'))
        );

        $token->consume();

        $this->assertTrue($token->isConsumed());
        $this->assertFalse($token->isUsable());
    }

    public function test_consume_is_idempotent_keeping_the_first_consumption_time(): void
    {
        $token = PasswordResetToken::create(
            UserAccountId::generate(),
            'hash',
            (new DateTimeImmutable())->add(new DateInterval('PT1H'))
        );

        $token->consume();
        $firstConsumedAt = $token->consumedAt();

        $token->consume();

        $this->assertSame($firstConsumedAt, $token->consumedAt());
    }
}
