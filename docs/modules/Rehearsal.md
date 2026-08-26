# Rehearsal Management Module

Status: **Implemented** (Backend + Web), and the **first Module** to
formally adopt the Core/Module Architecture's Contract boundary
(`docs/architecture/CoreModuleArchitecture.md`) - the template the
Ticket and Accounting Modules should follow as they mature.

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

## Core Contract usage (adopted this round)

`CreateRehearsalUseCase` and `ConfirmRehearsalUseCase` now depend on
`StageArt\Core\Contract\MembershipContract`
(`activeProductionMemberPersonIds(ProductionId): PersonId[]`) instead of
the former `Application\Rehearsal\ProductionMemberResolver` (deleted -
its logic moved unchanged into `StageArt\Core\Adapter\CoreMembershipAdapter`,
the concrete Core-side implementation). This is verified two ways:

- `tests/Core/RehearsalModuleDependencyDirectionTest.php` scans every
  Rehearsal Module source file and fails if any imports a concrete
  `StageArt\Infrastructure\*` class directly.
- `tests/Application/Rehearsal/RehearsalModuleContractIsolationTest.php`
  runs `CreateRehearsalUseCase` against a hand-written
  `FakeMembershipContract` that never touches a real Participant
  repository at all, proving the dependency is genuinely satisfied
  through the interface.

All other Rehearsal-side UseCases (13 in total, across
`Application/Rehearsal/`, `RehearsalAttendance/`, `ScheduleComment/`,
`Timetable/`, `TimetableItem/`) still depend on
`ProductionAuthorizationService`/`ProductionRepositoryInterface`
directly for authorization/production-lookup - not yet routed through
`AuthorizationContract`/`ProductionContextContract`. This is a
disclosed, deliberate scope limit (`03-ModularArchitecture.md`'s own
"don't over-abstract Web β" guidance), not a claim that every
dependency has been moved.

## Owned data

`stageart_rehearsals`, `stageart_rehearsal_attendances`,
`stageart_timetables`, `stageart_timetable_items`,
`stageart_timetable_item_participants`, `stageart_schedule_comments`.
None of these are read or written by Core code paths outside the
Rehearsal Application/Domain namespaces.

## API boundary

Routes are flat under `stageart/v1/rehearsals`,
`stageart/v1/productions/{id}/rehearsals`, etc. - not prefixed
`/rehearsal/...`. Existing routes were not renamed this round (see
`docs/architecture/CoreModuleArchitecture.md` §13).

## Authorization (changed this round)

`ProductionAuthorizationService::canManageRehearsals()` - a Core method
named after this Module - has been **removed**. All 13 call sites now
call the generic `hasProductionCapability($person, $production,
RehearsalCapability::MANAGE)`, where `RehearsalCapability::MANAGE =
'Rehearsal.Update'` is owned by
`plugin/src/Application/Rehearsal/RehearsalCapability.php`, not by
Core. Behavior is unchanged (same underlying Role/Permission Set
lookup) - `tests/Core/Adapter/CoreAuthorizationAdapterTest.php` and the
pre-existing `RehearsalUseCaseTest.php` both confirm this.

## Known remaining coupling

`TimetableVersionPublishedNotification` (a Rehearsal/Timetable-specific
notification type) lives in Core's own `Domain\Notification` namespace,
not under a Rehearsal namespace. Not moved this round - it is a
persisted Domain Entity/table, and renaming it is a riskier migration-
like change than this phase's scope justified. `NotificationContract`
is defined (`plugin/src/Core/Contract/NotificationContract.php`) for
future Modules to build against, but has no adapter yet since no
generic Notification-creation mechanism currently exists to wrap.

## Conclusion

No structural blocker to future extraction was found. Membership
resolution and Authorization are both now genuinely Contract-based (the
latter via the shared, Module-name-free `AuthorizationContract`); the
remaining direct dependencies (`ProductionRepositoryInterface`,
`ProductionAuthorizationService` itself as opposed to
`AuthorizationContract`) are disclosed as not-yet-migrated rather than
claimed complete.
