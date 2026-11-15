<?php

declare(strict_types=1);

namespace StageArt\Domain\Reservation;

use InvalidArgumentException;

/**
 * Reservation.md v6.0 "# Reservation Number": "ReservationNumberは、
 * 利用者へ表示する予約番号として利用する...ReservationIdとは別の識別子と
 * して扱う". Phase 3 instruction §10 confirms this is the identifier a
 * general audience member actually uses (paired with their booking
 * email) for self-service change/cancel - unlike ReservationId (a UUID,
 * internal-only), this must be short enough to read back over a phone or
 * type into a form.
 *
 * Generated from a restricted alphabet that excludes visually ambiguous
 * characters (0/O, 1/I/L) to reduce transcription errors.
 */
final class ReservationNumber
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LENGTH = 10;

    private string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Reservation number must not be empty.');
        }

        $this->value = strtoupper($trimmed);
    }

    public static function generate(): self
    {
        $alphabetLength = strlen(self::ALPHABET);
        $value = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $value .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return new self($value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
