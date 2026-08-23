<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionSchedule;

/**
 * $from/$to are optional ISO-8601 DateTime strings. Phase 3.5
 * instruction §18: the full "小屋入り日から撤収日まで" range is the
 * default (both null, no filtering); a caller (e.g. Mobile's "当日+
 * 翌日" basic view) can narrow the range without the UseCase needing to
 * know about any particular client's default window.
 */
final class ListProductionTimetableItemsQuery
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public ?string $from;
    public ?string $to;

    public function __construct(string $productionId, int $requestedByWordPressUserId, ?string $from = null, ?string $to = null)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->from = $from;
        $this->to = $to;
    }
}
