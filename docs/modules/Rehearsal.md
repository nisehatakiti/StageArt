# Rehearsal Management Module

Status: **Implemented** (Backend + Web). Reviewed this round against
`docs/03-ModularArchitecture.md`'s Core/Module boundary - findings below.

## Responsibility

Owns the稽古 (rehearsal) schedule/session lifecycle and per-member
attendance response for a Production - see
`docs/04-DomainModel/RehearsalManagementPolicy.md` and
`docs/04-DomainModel/Rehearsal.md` / `RehearsalAttendance.md` for the
actual business rules (unchanged this round).

## Entities owned by this Module

- `Rehearsal` (`plugin/src/Domain/Rehearsal/`)
- `RehearsalAttendance` (`plugin/src/Domain/RehearsalAttendance/`)
- `Timetable` / `TimetableItem` (`plugin/src/Domain/Timetable/`, `Domain/TimetableItem/`)
- `ScheduleComment` (shared by Rehearsal and TimetableItem targets)

## Connection point to Core

Every Rehearsal-side Entity references Core exclusively through opaque
identifiers - `ProductionId` on `Rehearsal`, `PersonId` on
`RehearsalAttendance` - never a Core Entity object, never a Core-owned
field embedded into a Rehearsal table. Confirmed by reading
`Rehearsal.php`, `RehearsalAttendance.php`, and the `stageart_rehearsals`
/ `stageart_rehearsal_attendances` table definitions in
`Infrastructure/WordPress/Schema/Installer.php`: both tables carry only
`production_id` / `person_id` / `rehearsal_id` as cross-references, no
duplicated Core data.

The Application layer's `ProductionMemberResolver`
(`plugin/src/Application/Rehearsal/ProductionMemberResolver.php`) plays
the role of `03-ModularArchitecture.md` §6's `ProductionContextProvider`
concept in practice - it is the single place Rehearsal use cases ask
Core "who are this Production's active members," rather than each use
case querying `ParticipantRepositoryInterface` inline. It is a concrete
resolver class, not a formally named/injected Interface+Adapter pair,
but it already isolates the dependency to one file.

## Owned data

`stageart_rehearsals`, `stageart_rehearsal_attendances`,
`stageart_timetables`, `stageart_timetable_items`,
`stageart_timetable_item_participants`, `stageart_schedule_comments`.
None of these are read or written by Core code paths outside the
Rehearsal Application/Domain namespaces.

## API boundary

Routes are flat under `stageart/v1/rehearsals`,
`stageart/v1/productions/{id}/rehearsals`, etc. - not prefixed
`/rehearsal/...` per `03-ModularArchitecture.md` §10's illustrative
convention. That section explicitly says not to break existing routes
retroactively ("実際の既存API構造を不用意に全面変更する必要はない"), so
this was left as-is; a `/rehearsal/` prefix is a candidate for *new*
routes only, not a required rename of what exists.

## Coupling finding (watch item, not fixed this round)

`ProductionAuthorizationService` - a **Core** Application service
(`plugin/src/Application/Production/ProductionAuthorizationService.php`)
- exposes module-named methods `canManageRehearsals()` and
`canManageAccounting()` directly. This means Core's own public surface
has hard-coded knowledge of two Module names. The underlying mechanism
each method calls, `hasProductionPermission($person, $production,
Permission::fromString('Rehearsal.Update'))`, is already generic (a
Role -> Permission-string lookup) - only the wrapper method names are
Module-specific.

This was not judged severe enough to fix this round (`03-ModularArchitecture.md`
§8: "Web β版では実装を過度に抽象化して開発速度を落とす必要はない...後から
切り出せない密結合を新たに作らないことを優先する" - this is pre-existing
from a prior phase, not new coupling introduced this round, and the
underlying data/logic is not duplicated into Core). Recommended future
cleanup: have Rehearsal/Accounting Application code call the already-
generic `hasProductionPermission()` directly instead of Core exposing
one bespoke `canManage*()` method per Module, so Core's surface doesn't
grow one method per future Module.

## Conclusion

No structural blocker to future extraction was found. The one coupling
finding above is a naming/API-surface smell on a Core service, not a
data or business-rule leak, and does not block the current Web β work.
