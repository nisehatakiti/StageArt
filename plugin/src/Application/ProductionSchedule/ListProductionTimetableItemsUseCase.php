<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionSchedule;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\TimetableItem\TimetableItemResult;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItem;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * Production Schedule Read Model (Timetable.md's "Timetableの物理的
 *所属とProduction全体表示の関係"): Timetable itself stays parented to
 * Rehearsal (unchanged Cardinality - see Timetable::class), but this
 * UseCase aggregates TimetableItems across every Rehearsal belonging to
 * a Production into one chronologically-sorted list. No new persistent
 * Entity or table is introduced here - this is a pure read-side
 * aggregation over the existing Rehearsal/Timetable/TimetableItem data,
 * matching the precedent already set by Rehearsal.md's own "Production
 * Schedule (Read Model)" section.
 *
 * Like ListTimetableItemsUseCase, this must never filter by the
 * requester's own Role/ParticipantType - only Production Scope
 * membership gates access, and every Production Member receives the
 * identical aggregate result.
 *
 * Only each Rehearsal's current PUBLISHED Version is aggregated
 * (findPublishedByRehearsalId) - DRAFT Versions never appear here, per
 * Timetable.md's "Production Scheduleとの関係" ("DRAFT版・ARCHIVED版は、
 * 通常のProduction Schedule表示には含めない"). An optional [from, to]
 * window narrows the result (e.g. Mobile's "当日+翌日" default view);
 * omitting both returns the full Production period.
 *
 * StageArt Core/Module Architecture Phase 3: migrated from
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService` to
 * Core Contracts - invoked from `TimetableRestController`, itself
 * Rehearsal-owned, so this UseCase should not depend on Core internals
 * directly either.
 */
final class ListProductionTimetableItemsUseCase
{
    private ProductionContextContract $productionContext;
    private RehearsalRepositoryInterface $rehearsals;
    private TimetableRepositoryInterface $timetables;
    private TimetableItemRepositoryInterface $items;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        ProductionContextContract $productionContext,
        RehearsalRepositoryInterface $rehearsals,
        TimetableRepositoryInterface $timetables,
        TimetableItemRepositoryInterface $items,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->productionContext = $productionContext;
        $this->rehearsals = $rehearsals;
        $this->timetables = $timetables;
        $this->items = $items;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    /**
     * @return TimetableItemResult[]
     */
    public function execute(ListProductionTimetableItemsQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ProductionScheduleAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new ProductionScheduleAccessDeniedException(
                'You must be a member of this Production to view its Production Schedule.'
            );
        }

        $from = $this->parseOptionalDateTime($query->from);
        $to = $this->parseOptionalDateTime($query->to);

        $allItems = [];

        foreach ($this->rehearsals->findByProductionId($productionId) as $rehearsal) {
            $timetable = $this->timetables->findPublishedByRehearsalId($rehearsal->id());

            if ($timetable === null) {
                continue;
            }

            foreach ($this->items->findByTimetableId($timetable->id()) as $item) {
                if ($from !== null && $item->startDateTime() < $from) {
                    continue;
                }

                if ($to !== null && $item->startDateTime() > $to) {
                    continue;
                }

                $allItems[] = $item;
            }
        }

        usort(
            $allItems,
            static fn (TimetableItem $a, TimetableItem $b): int => $a->startDateTime() <=> $b->startDateTime()
        );

        return array_map(static fn (TimetableItem $item) => TimetableItemResult::fromDomain($item), $allItems);
    }

    private function parseOptionalDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $parsed = new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid date/time value: {$value}");
        }

        // TimetableItem's stored start/end date-times are persisted as bare
        // wall-clock strings (see WordPressTimetableItemRepository::save(),
        // which writes DateTimeImmutable::format('Y-m-d H:i:s') and thereby
        // drops whatever offset the value carried) and are re-hydrated
        // without an explicit timezone, so they always compare as naive
        // wall-clock values. A from/to bound supplied with an explicit
        // offset (e.g. "+09:00") must be reduced to the same wall-clock
        // convention before comparing, or it silently compares against the
        // wrong instant whenever the offset differs from the server's
        // default timezone. This mirrors the existing storage convention;
        // it does not fix the deeper pre-existing timezone-handling gap.
        return new DateTimeImmutable($parsed->format('Y-m-d H:i:s'));
    }
}
