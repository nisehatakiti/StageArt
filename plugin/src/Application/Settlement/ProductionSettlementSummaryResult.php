<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

final class ProductionSettlementSummaryResult
{
    /** @var ProductionMemberSettlementLineResult[] */
    public array $members;
    public int $quotaShortfallCount;
    public int $quotaShortfallPayable;

    /**
     * @param ProductionMemberSettlementLineResult[] $members
     */
    public function __construct(array $members, int $quotaShortfallCount, int $quotaShortfallPayable)
    {
        $this->members = $members;
        $this->quotaShortfallCount = $quotaShortfallCount;
        $this->quotaShortfallPayable = $quotaShortfallPayable;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'members' => array_map(static fn (ProductionMemberSettlementLineResult $line): array => $line->toArray(), $this->members),
            'quota_shortfall_count' => $this->quotaShortfallCount,
            'quota_shortfall_payable' => $this->quotaShortfallPayable,
        ];
    }
}
