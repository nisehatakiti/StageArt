<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

/**
 * R14-B §6.4 (D6, U9): the one comparison form of an e-mail address -
 * surrounding whitespace removed and the whole address lower-cased.
 * Stored values are kept as entered (trimmed); this form is used only to
 * compare, everywhere an address is matched for an invitation (the
 * recipient check, the invitee's own invitation list, and later the
 * duplicate / 30-day checks).
 *
 * The database's utf8mb4_unicode_520_ci `=` also equates accents, full-
 * and half-width forms and trailing spaces, which this does not: a SQL
 * match is only ever a candidate, and a match is decided by comparing
 * this form in PHP.
 */
final class InvitationEmailAddress
{
    private function __construct()
    {
    }

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function same(string $a, string $b): bool
    {
        $normalizedA = self::normalize($a);

        return $normalizedA !== '' && $normalizedA === self::normalize($b);
    }
}
