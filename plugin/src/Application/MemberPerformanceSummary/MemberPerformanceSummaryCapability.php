<?php

declare(strict_types=1);

namespace StageArt\Application\MemberPerformanceSummary;

/**
 * Phase 5 (Production運営UI §9): the Member Performance Summary
 * combines per-member Rehearsal attendance history with individually-
 * attributed ticket sales figures - the same sensitivity class as
 * Settlement/Accounting (individually-identifying, money-adjacent data
 * about every member, not just the viewer). No RoleKey grants this in
 * `RolePermissions::MAP`, so it defaults to PrimaryManager-only via
 * `AuthorizationContract::canForProduction()`, mirroring
 * `SettlementCapability`/`AccountingCapability`'s own precedent exactly.
 */
final class MemberPerformanceSummaryCapability
{
    public const VIEW = 'MemberPerformanceSummary.View';

    private function __construct()
    {
    }
}
