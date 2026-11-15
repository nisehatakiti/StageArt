<?php

declare(strict_types=1);

namespace StageArt\Domain\Ticket;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Phase 3 instruction §8: sales end is never a fixed absolute datetime
 * persisted per Performance - it is one Production-wide rule, resolved
 * to an actual datetime per-Performance from that Performance's own
 * start datetime. Exactly two rules are supported this Phase (§8):
 *
 * - DAY_BEFORE_AT_TIME ("公演前日の指定時刻まで"): parameter is a wall-
 *   clock time "H:i" (e.g. "23:00").
 * - HOURS_BEFORE_START ("開演の指定時間前まで"): parameter is a positive
 *   integer number of hours (e.g. "3").
 *
 * `Production::ticketSalesEndRule()`/`ticketSalesEndParameter()` store
 * these as plain strings (Core does not depend on this Module's
 * vocabulary - see Production::class's own docblock); this Value Object
 * is where the Ticket Module actually validates and interprets them.
 */
final class SalesEndRule
{
    public const DAY_BEFORE_AT_TIME = 'DAY_BEFORE_AT_TIME';
    public const HOURS_BEFORE_START = 'HOURS_BEFORE_START';

    private const VALID_RULES = [
        self::DAY_BEFORE_AT_TIME,
        self::HOURS_BEFORE_START,
    ];

    private string $rule;
    private string $parameter;

    private function __construct(string $rule, string $parameter)
    {
        $this->rule = $rule;
        $this->parameter = $parameter;
    }

    public static function dayBeforeAtTime(string $time): self
    {
        $trimmed = trim($time);

        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $trimmed)) {
            throw new InvalidArgumentException("Invalid time for DAY_BEFORE_AT_TIME rule (expected H:i): {$time}");
        }

        return new self(self::DAY_BEFORE_AT_TIME, $trimmed);
    }

    public static function hoursBeforeStart(int $hours): self
    {
        if ($hours < 1) {
            throw new InvalidArgumentException('HOURS_BEFORE_START parameter must be a positive integer.');
        }

        return new self(self::HOURS_BEFORE_START, (string) $hours);
    }

    /**
     * Reconstructs from Production's own stored (rule, parameter) pair,
     * validating them the same way the two named factories above do -
     * this is the one place a persisted, previously-unvalidated pair is
     * turned back into a trustworthy Value Object.
     */
    public static function fromStored(string $rule, string $parameter): self
    {
        if (! in_array($rule, self::VALID_RULES, true)) {
            throw new InvalidArgumentException("Invalid SalesEndRule: {$rule}");
        }

        return $rule === self::DAY_BEFORE_AT_TIME
            ? self::dayBeforeAtTime($parameter)
            : self::hoursBeforeStart((int) $parameter);
    }

    /**
     * §8: "Productionの販売終了ルール + Performanceの開演日時 = 実際の
     * 販売終了日時" - computed fresh every time, never persisted per
     * Performance.
     */
    public function computeDeadline(DateTimeImmutable $performanceStartDateTime): DateTimeImmutable
    {
        if ($this->rule === self::HOURS_BEFORE_START) {
            return $performanceStartDateTime->modify("-{$this->parameter} hours");
        }

        [$hour, $minute] = explode(':', $this->parameter);

        return $performanceStartDateTime
            ->modify('-1 day')
            ->setTime((int) $hour, (int) $minute, 0);
    }

    public function rule(): string
    {
        return $this->rule;
    }

    public function parameter(): string
    {
        return $this->parameter;
    }
}
