# Rehearsal Management Module

Status: **Implemented** (Backend + Web), **fully Contract-adopted**
(Phase 2), and, as of Phase 3, **package-boundary-consolidated** - the
first Module with its own `ModuleDescriptor`/`*ModuleBootstrap`/
`*Installer` (`plugin/src/Rehearsal/`), proven extractable into a
separate WordPress Plugin by real Bootstrap Isolation Tests, not just
claimed in docs. See `docs/architecture/WordPressPluginModuleBoundary.md`
for the full Phase 3 detail; this file stays the per-Module summary.
The template the Ticket and Accounting Modules should follow.

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

## Package boundary (Phase 3)

`plugin/src/Rehearsal/` is a new, thin composition layer - it does
**not** relocate the Domain/Application/Infrastructure/Presentation
code above, which stays in its existing DDD-layered location:

- `RehearsalModuleBootstrap` - constructs all 26 UseCases and all 7 REST
  Controllers from Core Contracts + this Module's own Repository
  interfaces + `TransactionManagerInterface`. `Presentation\Plugin::boot()`
  is its only caller today.
- `RehearsalModuleDescriptor` - declares `moduleId() = 'rehearsal'`,
  its own `version()`, `requiredContracts()` (the 5 Contracts below),
  and `ownedTables()` (the 7 tables below). Registers into
  `Core\Module\ModuleRegistry`.
- `RehearsalInstaller` - owns this Module's 7 tables' `CREATE TABLE`/
  `ALTER TABLE` statements, called from Core's own
  `Infrastructure\WordPress\Schema\Installer::install()`.
- `RehearsalUpcomingRehearsalProvider` (Phase 4 §1) - the reverse
  direction from the three above: implements Core's own
  `Application\Dashboard\UpcomingRehearsalProviderInterface` Port, so
  Core's `GetMyDashboardUseCase` can get "this Person's upcoming
  Rehearsals" without importing
  `RehearsalRepositoryInterface`/`RehearsalAttendanceRepositoryInterface`
  itself. Exposed via `RehearsalModuleBootstrap::upcomingRehearsalProvider()`.
  See `docs/architecture/WordPressPluginModuleBoundary.md` §15.

## Core Contract usage (fully adopted)

All 26 Rehearsal-family UseCases depend on Core Contracts instead of
Core's Repository interfaces/Domain Entities/`ProductionAuthorizationService`
directly (24 migrated in Phase 2, 2 more found and migrated in Phase 3
- see below):

| UseCase group | Contracts depended on |
|---|---|
| Management ops (`ActivateRehearsalUseCase`, `CancelRehearsalUseCase`, `CompleteRehearsalUseCase`, `UpdateRehearsalUseCase`) | `ProductionContextContract`, `IdentityContract`, `AuthorizationContract` |
| Read ops (`GetRehearsalUseCase`, `ListRehearsalsUseCase`) | `ProductionContextContract`, `IdentityContract`, `MembershipContract` |
| Creation (`CreateRehearsalUseCase`, `ConfirmRehearsalUseCase`) | `ProductionContextContract`, `MembershipContract`, `IdentityContract`, `AuthorizationContract` |
| RehearsalAttendance UseCases (4) | `IdentityContract` (+ `MembershipContract`/`ProductionContextContract` where needed - `RespondRehearsalAttendanceUseCase` needs only `IdentityContract`, since it checks `$attendance->personId()->equals($requesterId)` directly) |
| Timetable/TimetableItem UseCases (12) | `ProductionContextContract`, `IdentityContract`, `AuthorizationContract`, plus `MembershipContract` where target-member resolution is needed (`TimetableItemTargetValidator`) |
| ScheduleComment UseCases (6) | `IdentityContract` (+ `AuthorizationContract` for manager-override deletes) |
| `GetProductionPrintViewUseCase`, `ListProductionTimetableItemsUseCase` (Phase 3) | `ProductionContextContract`, `IdentityContract`, `MembershipContract` - both back Rehearsal-owned REST Controllers (`PrintViewRestController`, part of `TimetableRestController`) but were missed by Phase 2's own count; migrated this phase so the Contract-adoption claim is true for everything this Module owns |

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
- `tests/Core/ModuleBoundaryDependencyTest.php` (Phase 3) - Rehearsal
  never imports Accounting's namespaces, and `src/Core/` never imports
  Rehearsal's Domain.
- `tests/Rehearsal/RehearsalModuleBootstrapIsolationTest.php` (Phase 3)
  - constructs the entire `RehearsalModuleBootstrap`-wired object graph
  (REST Controller -> UseCase -> Repository) using Fakes for every
  Contract and in-memory Fakes for every Repository, then pulls the
  real `CreateRehearsalUseCase` back out via Reflection and executes it
  - proving the full wiring works, not just that construction succeeds.

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

No structural blocker to future extraction into a separate WordPress
Plugin was found. Every Rehearsal-family UseCase is genuinely
Contract-based - Membership resolution, Production Context, Identity,
and Authorization all flow through `StageArt\Core\Contract\*` - and, as
of Phase 3, the Module's entire wiring (UseCase construction, REST
Controller registration, table migration) is consolidated into
`RehearsalModuleBootstrap`/`RehearsalInstaller`/`RehearsalModuleDescriptor`,
proven swap-able by a Bootstrap Isolation Test that runs the real,
fully-wired object graph against nothing but Fakes. Two disclosed
couplings remain, neither structural: `TimetableVersionPublishedNotification`'s
Core-side home (a considered design decision, not an oversight - see
above) and Core's own Dashboard/Notification-listing UseCases reading
this Module's Repository interfaces directly (a reverse-direction
coupling found during Phase 3's own re-investigation, not yet fixed -
see `docs/architecture/WordPressPluginModuleBoundary.md` §14).
