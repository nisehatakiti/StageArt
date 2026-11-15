<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

/**
 * Production Lifecycle Capability requested through
 * `ProductionAuthorizationService::hasProductionCapability()`.
 *
 * `Production.Complete` (ACTIVE -> COMPLETED, 決算確定) is granted to the
 * PrimaryManager, a 会計管理者 (RoleKey::ACCOUNTING_MANAGER via
 * `RolePermissions::MAP`) and a 代理人 (PARTICIPANT_MANAGER +
 * REHEARSAL_MANAGER, see `ProductionAuthorizationService`) - the same
 * holders as `AccountingCapability::APPROVE_EXPENSE` (StageArt 統合・会計
 * 仕様の確定事項 2026-10-28 §2).
 */
final class ProductionCapability
{
    public const COMPLETE = 'Production.Complete';

    private function __construct()
    {
    }
}
