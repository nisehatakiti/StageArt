<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Timetable\TimetableId;

/**
 * Phase 3.5 instruction §8/§9/§10: "Publish時のNotification発火基盤" -
 * a minimal, disclosed-scope foundation, not a full Notification
 * Center. This Entity is an immutable Fact ("a Timetable Version was
 * published, and the Production's members are the intended audience"),
 * created once inside PublishTimetableVersionUseCase's Transaction
 * Boundary. It records what happened, not how/whether delivery
 * happened - actual dispatch (Push vs in-app), read/unread state, and
 * per-recipient fan-out are explicitly out of scope this phase and are
 * left to a future Notification Domain (per-recipient rows, Push
 * preference filtering, etc. are not invented here - see the Phase 3.5
 * report's 未確定事項).
 *
 * Audience resolution (which Persons actually see this) is
 * deliberately NOT stored per-recipient here: Production membership is
 * already queryable via ProductionAuthorizationService /
 * ProductionMemberResolver, so a future delivery mechanism can expand
 * productionId -> members at send time rather than this Entity
 * duplicating that membership list.
 */
final class TimetableVersionPublishedNotification
{
    private TimetableVersionPublishedNotificationId $id;
    private ProductionId $productionId;
    private RehearsalId $rehearsalId;
    private TimetableId $timetableId;
    private int $version;
    private PersonId $publishedBy;
    private DateTimeImmutable $publishedAt;
    private ?string $changeSummary;
    private DateTimeImmutable $createdAt;

    private function __construct(
        TimetableVersionPublishedNotificationId $id,
        ProductionId $productionId,
        RehearsalId $rehearsalId,
        TimetableId $timetableId,
        int $version,
        PersonId $publishedBy,
        DateTimeImmutable $publishedAt,
        ?string $changeSummary,
        DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->rehearsalId = $rehearsalId;
        $this->timetableId = $timetableId;
        $this->version = $version;
        $this->publishedBy = $publishedBy;
        $this->publishedAt = $publishedAt;
        $this->changeSummary = $changeSummary;
        $this->createdAt = $createdAt;
    }

    public static function create(
        ProductionId $productionId,
        RehearsalId $rehearsalId,
        TimetableId $timetableId,
        int $version,
        PersonId $publishedBy,
        DateTimeImmutable $publishedAt,
        ?string $changeSummary
    ): self {
        return new self(
            TimetableVersionPublishedNotificationId::generate(),
            $productionId,
            $rehearsalId,
            $timetableId,
            $version,
            $publishedBy,
            $publishedAt,
            $changeSummary,
            new DateTimeImmutable()
        );
    }

    public static function reconstitute(
        TimetableVersionPublishedNotificationId $id,
        ProductionId $productionId,
        RehearsalId $rehearsalId,
        TimetableId $timetableId,
        int $version,
        PersonId $publishedBy,
        DateTimeImmutable $publishedAt,
        ?string $changeSummary,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            $id,
            $productionId,
            $rehearsalId,
            $timetableId,
            $version,
            $publishedBy,
            $publishedAt,
            $changeSummary,
            $createdAt
        );
    }

    public function id(): TimetableVersionPublishedNotificationId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function rehearsalId(): RehearsalId
    {
        return $this->rehearsalId;
    }

    public function timetableId(): TimetableId
    {
        return $this->timetableId;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function publishedBy(): PersonId
    {
        return $this->publishedBy;
    }

    public function publishedAt(): DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function changeSummary(): ?string
    {
        return $this->changeSummary;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
