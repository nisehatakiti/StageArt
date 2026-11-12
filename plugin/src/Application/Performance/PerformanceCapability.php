<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

/**
 * StageArt Core/Module Architecture: Performance Module's own Capability
 * vocabulary, requested from
 * `StageArt\Core\Contract\AuthorizationContract::canForProduction()`.
 *
 * Phase 2 Performance基盤 instruction §2/§17 explicitly names four
 * canonical Permission strings (Performance.Read/Create/Update/Cancel) as
 * the fixed vocabulary, unlike Rehearsal's single `RehearsalCapability::MANAGE`
 * umbrella covering every mutation - so each mutating operation here
 * checks its own matching string (CreatePerformanceUseCase checks CREATE,
 * UpdatePerformanceUseCase checks UPDATE, CancelPerformanceUseCase checks
 * CANCEL) rather than collapsing to one umbrella. There is no READ
 * constant: read access (Get/List) is gated by
 * `MembershipContract::isProductionMember()` instead, matching
 * Rehearsal's own read-access precedent (no Capability string is checked
 * for reads there either) - `Performance.Read` still appears in
 * `RolePermissions::MAP`'s PERFORMANCE_MANAGER Permission Set for
 * completeness with the Blueprint's own vocabulary, it is simply never
 * the subject of a runtime `canForProduction()` call.
 */
final class PerformanceCapability
{
    public const CREATE = 'Performance.Create';
    public const UPDATE = 'Performance.Update';
    public const CANCEL = 'Performance.Cancel';

    private function __construct()
    {
    }
}
