<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantEvent\ParticipantEventRepositoryInterface;

/** R14-B stage 3: the audit log in memory (append-only), with snapshot/restore and a write failure. */
final class InMemoryParticipantEventRepository implements ParticipantEventRepositoryInterface
{
    /** @var ParticipantEvent[] */
    private array $events = [];

    public ?\Throwable $failNextAppend = null;

    public function append(ParticipantEvent $event): void
    {
        if ($this->failNextAppend !== null) {
            $failure = $this->failNextAppend;
            $this->failNextAppend = null;

            throw $failure;
        }

        $this->events[] = $event;
    }

    /**
     * @return ParticipantEvent[]
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * @return string[]
     */
    public function types(): array
    {
        return array_map(static fn (ParticipantEvent $event): string => $event->eventType(), $this->events);
    }

    /**
     * @return ParticipantEvent[]
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter($this->events, static fn (ParticipantEvent $event): bool => $event->eventType() === $type));
    }

    /**
     * @return ParticipantEvent[]
     */
    public function snapshot(): array
    {
        return $this->events;
    }

    /**
     * @param ParticipantEvent[] $snapshot
     */
    public function restore(array $snapshot): void
    {
        $this->events = $snapshot;
    }
}
