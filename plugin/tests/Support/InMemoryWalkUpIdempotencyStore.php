<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\CheckIn\WalkUpDuplicateRequestException;
use StageArt\Application\CheckIn\WalkUpIdempotencyStoreInterface;
use StageArt\Domain\Reservation\ReservationId;

/**
 * Mirrors WordPressWalkUpIdempotencyStore's real UNIQUE-constraint
 * behavior: recording an already-used key throws
 * WalkUpDuplicateRequestException, so tests can exercise the same
 * race-handling path CreateWalkUpReservationUseCase relies on.
 */
final class InMemoryWalkUpIdempotencyStore implements WalkUpIdempotencyStoreInterface
{
    /** @var array<string, string> */
    private array $reservationIdsByKey = [];

    public function findReservationId(string $idempotencyKey): ?ReservationId
    {
        return isset($this->reservationIdsByKey[$idempotencyKey])
            ? ReservationId::fromString($this->reservationIdsByKey[$idempotencyKey])
            : null;
    }

    public function record(string $idempotencyKey, ReservationId $reservationId): void
    {
        if (isset($this->reservationIdsByKey[$idempotencyKey])) {
            throw new WalkUpDuplicateRequestException();
        }

        $this->reservationIdsByKey[$idempotencyKey] = $reservationId->toString();
    }
}
