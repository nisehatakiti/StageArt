<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/**
 * R14-B §6.7 (stage 4): the keys for `email_key` (HMAC-SHA-256 of the
 * normalised address, used only to count sends per address). The keys are
 * configuration, never stored in the database: STAGEART_EMAIL_KEYS (version
 * => key, every version still needed for the 30-day window) and
 * STAGEART_EMAIL_KEY_CURRENT (the version new sends use). A key value is
 * never logged or put into an exception message; a version name may be.
 */
interface EmailKeyringInterface
{
    /** The version new send records use, or null when it is not configured (or has no usable key). */
    public function currentVersion(): ?string;

    /** The key of a version, or null when that version is not in the keyring (or is not usable). */
    public function keyFor(string $version): ?string;
}
