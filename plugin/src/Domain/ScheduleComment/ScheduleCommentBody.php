<?php

declare(strict_types=1);

namespace StageArt\Domain\ScheduleComment;

use InvalidArgumentException;

final class ScheduleCommentBody
{
    private const MAX_LENGTH = 500;

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('ScheduleComment body must not be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('ScheduleComment body must not exceed ' . self::MAX_LENGTH . ' characters.');
        }

        $this->value = $trimmed;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
