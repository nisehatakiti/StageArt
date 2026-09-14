<?php

declare(strict_types=1);

namespace StageArt\Application\MemberPerformanceSummary;

final class MemberPerformanceSummaryResult
{
    /** @var MemberPerformanceSummaryLineResult[] */
    public array $members;

    /**
     * @param MemberPerformanceSummaryLineResult[] $members
     */
    public function __construct(array $members)
    {
        $this->members = $members;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'members' => array_map(static fn (MemberPerformanceSummaryLineResult $line): array => $line->toArray(), $this->members),
        ];
    }
}
