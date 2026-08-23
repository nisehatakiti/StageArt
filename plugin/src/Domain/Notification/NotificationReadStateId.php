<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use InvalidArgumentException;
use StageArt\Domain\Shared\Uuid;

final class NotificationReadStateId
{
    private string $value;

    private function __construct(string $value)
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid NotificationReadStateId: {$value}");
        }

        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(Uuid::generate());
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
