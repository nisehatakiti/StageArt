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

    /**
     * docs/20-RehearsalManagementScreen.md §9.1.
     */
    public function test_planned_message(): void
    {
        $message = RehearsalNotificationMessageBuilder::buildPlannedMessage('第10回公演', new DateTimeImmutable('2026-09-20'));

        $this->assertSame('第10回公演の稽古が2026/09/20に計画されています。詳細を確認して出欠登録をお願いします', $message);
    }

    /**
     * §9.2: "リマインド文面は元通知の先頭に【Remind】を付ける". PR #7 R9
     * implemented the original (§9.1) notification, so the reminder now
     * prefixes that text; before R9 it prefixed the response-request text
     * because no §9.1 notification existed.
     */
    public function test_reminder_message_prefixes_the_planned_message(): void
    {
        $message = RehearsalNotificationMessageBuilder::buildReminderMessage('第10回公演', new DateTimeImmutable('2026-09-20'));

        $this->assertSame('【Remind】第10回公演の稽古が2026/09/20に計画されています。詳細を確認して出欠登録をお願いします', $message);
    }

    /**
     * §11 (the date uses the same Y/m/d format as the other messages).
     */
    public function test_confirmed_message(): void
    {
        $message = RehearsalNotificationMessageBuilder::buildConfirmedMessage('第10回公演', new DateTimeImmutable('2026-09-20'));

        $this->assertSame('第10回公演の稽古日程が2026/09/20で確定しました。詳細を確認して出欠登録をお願いします', $message);
    }

    public function test_cancelled_message(): void
    {
        $message = RehearsalNotificationMessageBuilder::buildCancelledMessage('第10回公演', new DateTimeImmutable('2026-09-20'));

        $this->assertSame('第10回公演の2026/09/20の稽古は中止となりました', $message);
    }
}
