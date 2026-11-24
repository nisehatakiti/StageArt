<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

/**
 * Production Lifecycle Capability requested through
 * `ProductionAuthorizationService::hasProductionCapability()`.
 *
 * `Production.Complete` (ACTIVE -> COMPLETED, 公演の完了) is granted to no
 * Role and to no 代理人 rule (PR #8 統合, 2026-11-24): completion is
 * PrimaryManager-only, and `ProductionAuthorizationService::
 * canCompleteProduction()` checks the PrimaryManager directly. Until then
 * 会計管理者 and 代理人 held it too (統合・会計仕様の確定事項 2026-10-28 §2).
 * The constant stays as the Capability name for this transition.
 */
final class ProductionCapability
{
    public const COMPLETE = 'Production.Complete';

    private function __construct()
    {
    }
}
