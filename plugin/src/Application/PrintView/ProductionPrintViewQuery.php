<?php

declare(strict_types=1);

namespace StageArt\Application\PrintView;

/**
 * Deliberately has no paperSize/orientation field: those are
 * Presentation/Layout attributes (Timetable.md §3 - "用紙サイズ・方向は
 * Presentation / Layout上の属性として扱う"), not part of the data query.
 * This Query only decides WHICH data to fetch (Production-wide,
 * Published-only, no Role/Person filter); HOW it is laid out on a given
 * paper size/orientation is decided later, by the Presentation-layer
 * renderer, from the same result every time.
 */
final class ProductionPrintViewQuery
{
    public string $productionId;
    public int $requestedByWordPressUserId;

    public function __construct(string $productionId, int $requestedByWordPressUserId)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
