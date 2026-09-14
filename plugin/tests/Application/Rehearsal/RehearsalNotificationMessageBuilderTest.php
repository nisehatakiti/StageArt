<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Rehearsal\RehearsalNotificationMessageBuilder;

final class RehearsalNotificationMessageBuilderTest extends TestCase
{
    public function test_response_request_message(): void
    {
        $message = RehearsalNotificationMessageBuilder::buildResponseRequestMessage('第10回公演', new DateTimeImmutable('2026-09-20'));

        $this->assertSame('第10回公演の2026/09/20の稽古の出欠を回答してください', $message);
    }

    public function test_reminder_message_prefixes_the_response_request_message(): void
    {
        $message = RehearsalNotificationMessageBuilder::buildReminderMessage('第10回公演', new DateTimeImmutable('2026-09-20'));

        $this->assertSame('【Remind】第10回公演の2026/09/20の稽古の出欠を回答してください', $message);
    }

    public function test_cancelled_message(): void
    {
        $message = RehearsalNotificationMessageBuilder::buildCancelledMessage('第10回公演', new DateTimeImmutable('2026-09-20'));

        $this->assertSame('第10回公演の2026/09/20の稽古は中止となりました', $message);
    }
}
