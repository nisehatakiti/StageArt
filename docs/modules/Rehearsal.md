# Rehearsal Management Module

Status: **Implemented** (Backend + Web), and **fully Contract-adopted**
as of Phase 2 - the first Module to depend on Core exclusively through
`StageArt\Core\Contract\*`
(`docs/architecture/CoreModuleArchitecture.md`), the template the
Ticket and Accounting Modules should follow.

## Responsibility

Owns the稽古 (rehearsal) schedule/session lifecycle and per-member
attendance response for a Production - see
`docs/04-DomainModel/RehearsalManagementPolicy.md` and
`docs/04-DomainModel/Rehearsal.md` / `RehearsalAttendance.md` for the
actual business rules (unchanged this phase).

## Entities owned by this Module

- `Rehearsal` (`plugin/src/Domain/Rehearsal/`)
- `RehearsalAttendance` (`plugin/src/Domain/RehearsalAttendance/`)
- `Timetable` / `TimetableItem` (`plugin/src/Domain/Timetable/`, `Domain/TimetableItem/`)
- `ScheduleComment` (shared by Rehearsal and TimetableItem targets)

## Core Contract usage (fully adopted this phase)

All 24 Rehearsal-family UseCases now depend on Core Contracts instead
of Core's Repository interfaces/Domain Entities/`ProductionAuthorizationService`
directly:

| UseCase group | Contracts depended on |
|---|---|
| Management ops (`ActivateRehearsalUseCase`, `CancelRehearsalUseCase`, `CompleteRehearsalUseCase`, `UpdateRehearsalUseCase`) | `ProductionContextContract`, `IdentityContract`, `AuthorizationContract` |
| Read ops (`GetRehearsalUseCase`, `ListRehearsalsUseCase`) | `ProductionContextContract`, `IdentityContract`, `MembershipContract` |
| Creation (`CreateRehearsalUseCase`, `ConfirmRehearsalUseCase`) | `ProductionContextContract`, `MembershipContract`, `IdentityContract`, `AuthorizationContract` |
| RehearsalAttendance UseCases (4) | `IdentityContract` (+ `MembershipContract`/`ProductionContextContract` where needed - `RespondRehearsalAttendanceUseCase` needs only `IdentityContract`, since it checks `$attendance->personId()->equals($requesterId)` directly) |
| Timetable/TimetableItem UseCases (12) | `ProductionContextContract`, `IdentityContract`, `AuthorizationContract`, plus `MembershipContract` where target-member resolution is needed (`TimetableItemTargetValidator`) |
| ScheduleComment UseCases (6) | `IdentityContract` (+ `AuthorizationContract` for manager-override deletes) |

A UseCase receives a `ProductionId`/`PersonId`, never a `Production`/
`Person` Domain Entity - it does not need to understand Core's Entity
structure to do its job. `TimetableItemTargetValidator` migrated from
`ParticipantRepositoryInterface` to
`MembershipContract::activeProductionMemberPersonIds()`.

`PublishTimetableVersionUseCase` additionally calls
`NotificationContract::notify()` once per active Production member
(resolved via `MembershipContract`) when a Timetable Version is
published - the concrete "at least one existing notification migrated
through the Contract path" proof (see Known remaining coupling below).

Verified two ways:

- `tests/Core/RehearsalModuleDependencyDirectionTest.php` - no
  concrete `StageArt\Infrastructure\*` import.
- `tests/Core/ModuleDependencyDirectionTest.php` - no direct import of
  `ProductionRepositoryInterface`, `ParticipantRepositoryInterface`,
  `PersonRepositoryInterface`, or the `Production`/`Participant`/
  `Person` Domain Entities, across every Rehearsal-family
  Application/Domain directory.
- `tests/Application/Rehearsal/RehearsalModuleContractIsolationTest.php`
  runs `CreateRehearsalUseCase` end to end (authorization, target-member
  resolution, Rehearsal + Phase 1 Attendance creation) against
  hand-written Fakes for all four Contracts
  (`FakeProductionContextContract`, `FakeIdentityContract`,
  `FakeAuthorizationContract`, `FakeMembershipContract`) - **zero** real
  Core Repository is touched.

## Owned data

`stageart_rehearsals`, `stageart_rehearsal_attendances`,
`stageart_timetables`, `stageart_timetable_items`,
`stageart_timetable_item_participants`, `stageart_schedule_comments`.
None of these are read or written by Core code paths outside the
Rehearsal Application/Domain namespaces. `stageart_timetable_version_published_notifications`
is a partial exception - see Known remaining coupling.

## API boundary

Routes are flat under `stageart/v1/rehearsals`,
`stageart/v1/productions/{id}/rehearsals`, etc. - not prefixed
`/rehearsal/...`. No route was renamed, moved, or had its
request/response shape changed by Phase 2 - every REST Controller still
calls the same UseCase class it always did; only that UseCase's own
internals changed.

## Authorization

`ProductionAuthorizationService::canManageRehearsals()` - a Core method
named after this Module - was removed in Phase 1. This phase completed
the migration: every one of the 24 UseCases now calls
`AuthorizationContract::canForProduction($personId, $productionId,
RehearsalCapability::MANAGE)` (ID-based) rather than
`ProductionAuthorizationService::hasProductionCapability($person,
$production, ...)` (Entity-based), where `RehearsalCapability::MANAGE =
'Rehearsal.Update'` is owned by
`plugin/src/Application/Rehearsal/RehearsalCapability.php`, not by
Core. Behavior is unchanged (same underlying Role/Permission Set
lookup, delegated to `ProductionAuthorizationService` inside
`CoreAuthorizationAdapter`) - the full existing `RehearsalUseCaseTest.php`
suite plus the new isolation/dependency-direction tests confirm this.

## Notification usage

`PublishTimetableVersionUseCase` calls
`NotificationContract::notify($memberId, 'timetable_version_published',
[...])` for each active Production member. `NotificationContract` is
implemented by `CoreNotificationAdapter`, which delegates to
`Infrastructure\WordPress\Notification\WordPressNotificationDispatcher`
(fires `do_action('stageart_notification', ...)`). No listener is
registered on that hook yet - disclosed honestly, not a claim of end-
to-end delivery; see `CoreModuleArchitecture.md` §14.

## Known remaining coupling

`TimetableVersionPublishedNotification` stays in Core's own
`Domain\Notification` namespace. Reconsidered explicitly this phase
(not left in place merely because moving it felt risky): Core's own
`Application\Notification\ListNotificationsForProductionUseCase`,
`MarkNotificationReadUseCase`, and `Application\Dashboard\
GetMyDashboardUseCase` all query
`TimetableVersionPublishedNotificationRepositoryInterface` directly, as
part of Core's own cross-cutting Notification Feed. Moving the Entity
into a Rehearsal namespace would make Core's own UseCases depend on a
Module's concrete Domain class - backwards. `PublishTimetableVersionUseCase`
still creates/saves this Entity directly (legitimate: it is Rehearsal's
own code creating a Core-owned, disclosed-shared Fact type) and now
additionally notifies through `NotificationContract`. The larger fix (a
multi-producer Notification Fact abstraction so Core's feed doesn't
hardcode this one Entity type) is out of scope this phase.

## Conclusion

No structural blocker to future extraction was found. Every Rehearsal-
family UseCase is now genuinely Contract-based - Membership resolution,
Production Context, Identity, and Authorization all flow through
`StageArt\Core\Contract\*`, proven both by a namespace-scanning
Architecture Test and a Fake-Contract isolation test that runs real
business logic with no Core Repository at all. The one remaining,
disclosed coupling (`TimetableVersionPublishedNotification`'s Core-side
home) is a considered design decision, not an oversight.
