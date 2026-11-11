<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;

/**
 * Phase 7 (Rehearsal仕様整合): no server-side notification text builder
 * existed anywhere in the codebase before this (confirmed by survey -
 * the one other notification type, `timetable_version_published`, is
 * text-formatted entirely client-side). Date formatting follows the
 * existing `Y/m/d` convention found in `PrintViewHtmlRenderer` rather
 * than inventing a new one, per this Phase's explicit instruction not
 * to invent a different date format.
 *
 * PR #7 R9: the §9.1 (created) and §11 (confirmed) texts of
 * docs/20-RehearsalManagementScreen.md. As with the cancel text, the
 * date uses the same Y/m/d format (§11 and §17 write "YY/MM/DD"), and the
 * closing "。" is left off like the other messages.
 */
final class RehearsalNotificationMessageBuilder
{
    private const DATE_FORMAT = 'Y/m/d';

    /**
     * The R8 notification to members added after creation (§6.2).
     */
    public static function buildResponseRequestMessage(string $productionName, DateTimeImmutable $rehearsalDate): string
    {
        return "{$productionName}の" . $rehearsalDate->format(self::DATE_FORMAT) . 'の稽古の出欠を回答してください';
    }

    /**
     * §9.1: 「○○の稽古がYYYY/MM/DDに計画されています。詳細を確認して出欠登録をお願いします。」
     */
    public static function buildPlannedMessage(string $productionName, DateTimeImmutable $rehearsalDate): string
    {
        return "{$productionName}の稽古が" . $rehearsalDate->format(self::DATE_FORMAT) . 'に計画されています。詳細を確認して出欠登録をお願いします';
    }

    /**
     * §9.2: "リマインド文面は元通知の先頭に【Remind】を付ける" - the original
     * notification is §9.1's, so the reminder is exactly that text with
     * the prefix (R9; before R9 there was no §9.1 notification and the
     * prefix was put on the response-request text instead).
     */
    public static function buildReminderMessage(string $productionName, DateTimeImmutable $rehearsalDate): string
    {
        return '【Remind】' . self::buildPlannedMessage($productionName, $rehearsalDate);
    }

    /**
     * §11: 「○○の稽古日程がYY/MM/DDで確定しました。詳細を確認して出欠登録をお願いします。」
     */
    public static function buildConfirmedMessage(string $productionName, DateTimeImmutable $rehearsalDate): string
    {
        return "{$productionName}の稽古日程が" . $rehearsalDate->format(self::DATE_FORMAT) . 'で確定しました。詳細を確認して出欠登録をお願いします';
    }

    public static function buildCancelledMessage(string $productionName, DateTimeImmutable $rehearsalDate): string
    {
        return "{$productionName}の" . $rehearsalDate->format(self::DATE_FORMAT) . 'の稽古は中止となりました';
    }
}
