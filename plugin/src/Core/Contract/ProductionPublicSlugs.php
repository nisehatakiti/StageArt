<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

/**
 * アンケート実装指示書 §5/§49: the read-only slug pair a Module needs to
 * build the public `stageart.top/{organization-slug}/{production-slug}`
 * URL family - deliberately its own small Contract-layer type (matching
 * ProductionSummary/ProductionTicketSettings's own precedent) rather than
 * growing ProductionSummary, since most Modules never need slugs at all.
 */
final class ProductionPublicSlugs
{
    public string $organizationSlug;
    public string $productionSlug;

    public function __construct(string $organizationSlug, string $productionSlug)
    {
        $this->organizationSlug = $organizationSlug;
        $this->productionSlug = $productionSlug;
    }
}
