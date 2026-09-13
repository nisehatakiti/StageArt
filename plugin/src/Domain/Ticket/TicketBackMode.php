<?php

declare(strict_types=1);

namespace StageArt\Domain\Ticket;

use InvalidArgumentException;

final class TicketBackMode
{
    public const PROGRESSIVE = 'PROGRESSIVE'; // 累進方式
    public const SEPARATED = 'SEPARATED';     // 分離方式

    private const VALID = [
        self::PROGRESSIVE,
        self::SEPARATED,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid TicketBackMode: {$value}");
        }

        $this->value = $value;
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
