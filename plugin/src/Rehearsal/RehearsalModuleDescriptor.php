<?php

declare(strict_types=1);

namespace StageArt\Rehearsal;

use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Core\Module\ModuleDescriptor;

/**
 * StageArt Core/Module Architecture Phase 3: the Rehearsal Module's own
 * declared identity and Core-facing boundary - see `ModuleDescriptor`'s
 * own docblock for what this is and is not. `requiredContracts()` is
 * cross-checked by `ModuleDependencyDirectionTest`, not just declared
 * here and left unverified.
 */
final class RehearsalModuleDescriptor implements ModuleDescriptor
{
    public function moduleId(): string
    {
        return 'rehearsal';
    }

    public function version(): string
    {
        return '3.0.0';
    }

    public function requiredCoreVersion(): string
    {
        return '0.1.0';
    }

    public function requiredContracts(): array
    {
        return [
            ProductionContextContract::class,
            IdentityContract::class,
            AuthorizationContract::class,
            MembershipContract::class,
            NotificationContract::class,
        ];
    }

    public function ownedTables(): array
    {
        return [
            'stageart_rehearsals',
            'stageart_rehearsal_attendances',
            'stageart_schedule_comments',
            'stageart_timetables',
            'stageart_timetable_items',
            'stageart_timetable_item_participants',
            'stageart_timetable_version_published_notifications',
        ];
    }
}
