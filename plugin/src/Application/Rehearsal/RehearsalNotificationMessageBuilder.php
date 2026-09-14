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
 */
final class RehearsalNotificationMessageBuilder
{
    private const DATE_FORMAT = 'Y/m/d';

    public static function buildResponseRequestMessage(string $productionName, DateTimeImmutable $rehearsalDate): string
    {
        return "{$productionName}の" . $rehearsalDate->format(self::DATE_FORMAT) . 'の稽古の出欠を回答してください';
    }

    /**
     * The confirmed "【Remind】" prefix rule - applied to the exact same
     * text `buildResponseRequestMessage()` produces, never a separately
     * worded message.
     */
    public static function buildReminderMessage(string $productionName, DateTimeImmutable $rehearsalDate): string
    {
        return '【Remind】' . self::buildResponseRequestMessage($productionName, $rehearsalDate);
    }

    public static function buildCancelledMessage(string $productionName, DateTimeImmutable $rehearsalDate): string
    {
        return "{$productionName}の" . $rehearsalDate->format(self::DATE_FORMAT) . 'の稽古は中止となりました';
    }
}
