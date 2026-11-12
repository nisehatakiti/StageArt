<?php

declare(strict_types=1);

namespace StageArt\Application\Dashboard;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;

/**
 * StageArt Core/Module Architecture Phase 4 §1: a Core-owned Port, the
 * inverse direction from every `StageArt\Core\Contract\*` interface
 * built in Phases 1-3 (there, a Module depends on a Contract Core
 * implements; here, Core's own `GetMyDashboardUseCase` depends on this
 * interface and a Domain Module implements it). Core defines this
 * interface because Core is the consumer - the Dashboard aggregate is
 * Core's own Read Model - but Core does not know or care which Module
 * (today: Rehearsal) actually answers it. Naming is deliberately
 * scoped to what the Dashboard needs ("upcoming rehearsals"), not to
 * "a Rehearsal Module exists" - Core has no reference to Rehearsal
 * anywhere in this namespace.
 *
 * Only one implementation exists today
 * (`StageArt\Rehearsal\RehearsalUpcomingRehearsalProvider`), wired in
 * by `Presentation\Plugin::boot()` - not a multi-producer registry.
 * Building one now, before a second Module has anything to contribute
 * to a Person's "upcoming schedule", would be exactly the kind of
 * premature, un-validated abstraction StageArt's own architecture
 * instructions warn against; this interface's job is only to let the
 * one real producer be swapped or removed without Core's own code
 * changing.
 */
interface UpcomingRehearsalProviderInterface
{
    /**
     * @return UpcomingRehearsalResult[]
     */
    public function findUpcomingRehearsalsForPerson(PersonId $personId, DateTimeImmutable $now, int $limit): array;
}
