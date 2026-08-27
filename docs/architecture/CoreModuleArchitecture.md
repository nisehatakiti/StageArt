# StageArt Core / Module Architecture

Status: Confirmed - Phase 3. Implements `03-ModularArchitecture.md`'s
policy concretely, in code: the Rehearsal Module and (as far as
possible) the Accounting Module depend on Core exclusively through
`StageArt\Core\Contract\*`, not on Core's Repository interfaces,
Repository implementations, or Domain Entities directly. This document
records what actually exists (files, classes, tables, routes, tests),
not just the intended policy.

Phase 3 pushed the same boundary one level further: from "a Module
depends on Core through Contracts" to "a Module's own wiring is
consolidated enough that it could be registered from a genuinely
separate WordPress Plugin" - see `docs/architecture/
WordPressPluginModuleBoundary.md` for the full Phase 3 detail
(`RehearsalModuleBootstrap`, `RehearsalInstaller`,
`RehearsalModuleDescriptor`, the Module-boundary Architecture Tests,
and the Bootstrap Isolation Tests that prove Rehearsal never touches a
real Core Repository even when fully wired). This document stays the
overall summary; that one is the Phase 3 deep-dive.

Phase 1 built the Contract layer and a Capability-based Authorization
boundary but left most call sites still calling Core's concrete
services directly. Phase 2's job was to make the dependency chain
**業務Module → Core Contract → Core Adapter → StageArt Core** real in
code, not just in this document.

---

# 1. Purpose

`03-ModularArchitecture.md` established the policy: StageArt Core stays
a generic platform; Ticket/Rehearsal/Accounting become independently
extractable Domain Modules. Phase 1 introduced the Contracts. Phase 2
made them the actual dependency boundary for the Rehearsal Module (in
full) and the Accounting Module (with one disclosed, narrow exception -
§8) - proven by an Architecture Test that scans real `use` statements,
and by Fake-Contract isolation tests that run each Module's business
logic with **zero** real Core Repository involved.

---

# 2. Dependency Diagram

```text
                 StageArt Core
        (Identity, Organization, Production,
         Membership, Authorization, Notification)
                       │
              Core Contracts (plugin/src/Core/Contract/)
      ┌────────────────┼──────────────────┬───────────────┐
      │                │                  │               │
  Rehearsal          Ticket           Accounting      (future
   Module          (boundary       (Contract-adopted    Modules)
 (fully Contract-   defined,       except one disclosed
  adopted)         not built)      Organization-scope
                                    branch - §8)
```

```text
                   Core Contract (interface)
                    ┌──────────┴──────────┐
                    │                     │
           CoreAuthorizationAdapter   (future)
           CoreMembershipAdapter      WordPressAdapter
           CoreProductionContextAdapter
           CoreIdentityAdapter
           CoreOrganizationContextAdapter
           CoreNotificationAdapter
                    │
            StageArt Core's own
        Application/Domain/Infrastructure
              (unchanged internals)
```

A Module depends on the Contract (an interface in
`StageArt\Core\Contract`), never on the Adapter class or Core's
internal Domain/Infrastructure directly for what the Contract already
covers. Today exactly one concrete Adapter set exists
(`StageArt\Core\Adapter\Core*Adapter`, implemented against this
single-host StageArt installation); a future `WordPressAdapter`
implementing the same interfaces against a different host's Identity/
Production/Membership model is the extension point this indirection
exists for (§12).

---

# 3. StageArt Core's responsibility

Unchanged from `03-ModularArchitecture.md` §3: Identity/Person,
Organization, Production Context, Membership (Organization Membership +
Production Participant), Authorization (Role/Permission/Capability),
Notification, Public Identity/Page, Join (Join Key, Membership/
Participation Request).

Core does not know what a Rehearsal, a Ticket, or an Accounting entry
is. It knows how to answer: who is this, does this Organization/
Production exist, is this Person a member, can this Person do
`$capability` here, and how to deliver a notification to a Person.

---

# 4. Core Contracts

`plugin/src/Core/Contract/` (interfaces) + `plugin/src/Core/Adapter/`
(the concrete StageArt-hosted implementation of each, thin wrappers
around Core's existing Application/Domain services).

| Contract | Method(s) | Adapter |
|---|---|---|
| `IdentityContract` | `resolveCurrentPersonId(int $wordPressUserId): ?PersonId` | `CoreIdentityAdapter` |
| `OrganizationContextContract` | `organizationExists(OrganizationId): bool` | `CoreOrganizationContextAdapter` |
| `ProductionContextContract` | `getProduction(ProductionId): ?ProductionSummary`, `getProductionOrganizationId(ProductionId): ?OrganizationId` | `CoreProductionContextAdapter` |
| `MembershipContract` | `activeProductionMemberPersonIds(ProductionId): PersonId[]`, `isProductionMember(PersonId, ProductionId): bool` | `CoreMembershipAdapter` |
| `AuthorizationContract` | `resolveCurrentPersonId(int): ?PersonId`, `canForProduction(PersonId, ProductionId, string $capability): bool`, `canForOrganization(PersonId, OrganizationId, string $capability): bool` (Phase 3) | `CoreAuthorizationAdapter` |
| `NotificationContract` | `notify(PersonId, string $type, array $payload): void` | `CoreNotificationAdapter` (new this phase - §14) |

`ProductionSummary` (`plugin/src/Core/Contract/ProductionSummary.php`)
stays deliberately minimal (`id`, `name`, `status`) - it does **not**
carry `organizationId` directly. Adding it directly was considered and
rejected: it would force every consumer of `getProduction()` (including
Rehearsal, which never needs an Organization) to also wire a working
`ProductionOrganizationResolver`/`ProjectRepositoryInterface` chain,
risking a `ProjectNotFoundException` in code paths that never touch
Organization at all. Instead, `getProductionOrganizationId()` is a
**separate** method, called only by the Accounting Module (which
genuinely needs it, to validate an Account belongs to the Production's
own Organization) - Rehearsal never calls it.

`isProductionMember()` is broader than
`activeProductionMemberPersonIds()`: true for the Production's
PrimaryManager, any ACTIVE ProductionDelegate, or any ACTIVE
Person-subject Participant. It replaces the old
`ProductionAuthorizationService::isProductionMember(Person, Production)`
call pattern used by every read-access UseCase that previously needed a
full `Production`/`Person` Entity pair just to ask this question.

**Adopted this phase**: all 24 Rehearsal-family UseCases (§6) and all
13 migrated Accounting UseCases (§8) depend on these Contracts, not on
`ProductionRepositoryInterface`/`ParticipantRepositoryInterface`/
`ProductionAuthorizationService`/the `Production`/`Participant`/`Person`
Domain Entities directly. Verified by `tests/Core/
ModuleDependencyDirectionTest.php` (§6/§8).

---

# 5. Capability-based Authorization

**Before Phase 1**: `ProductionAuthorizationService` (Core) had
`canManageRehearsals()` and `canManageAccounting()` - two methods named
after two different Modules, living inside Core.

**After Phase 1**: both are **removed**. In their place:

```php
// Core (Application\Production\ProductionAuthorizationService) - generic, no Module names:
public function hasProductionCapability(Person $person, Production $production, string $capability): bool
{
    return $this->isPrimaryManager($person, $production)
        || $this->hasProductionPermission($person, $production, Permission::fromString($capability));
}
```

```php
// Rehearsal Module (Application\Rehearsal\RehearsalCapability) - owns its own string:
final class RehearsalCapability
{
    public const MANAGE = 'Rehearsal.Update';
}
```

```php
// Accounting Module (Application\Accounting\AccountingCapability):
final class AccountingCapability
{
    public const MANAGE = 'Accounting.Update';
}
```

**Phase 2 change**: every Rehearsal and Accounting call site that used
to call `ProductionAuthorizationService::hasProductionCapability($person,
$production, $capability)` directly (needing a full `Person`/
`Production` Entity pair) now calls
`AuthorizationContract::canForProduction($personId, $productionId,
$capability)` instead - the ID-based Contract version, resolving
`Person`/`Production` only inside `CoreAuthorizationAdapter`, never
inside a Module. A capability string with no matching
`RolePermissions` entry (e.g. `Accounting.Update` today - no
`ACCOUNTING_MANAGER` `RoleKey` exists) simply evaluates to false for a
non-PrimaryManager caller; Core never needs to know a capability exists
ahead of time.

---

# 6. Rehearsal Module (fully Contract-adopted)

See `docs/modules/Rehearsal.md` for full detail. Summary:

- All 24 Rehearsal-family UseCases (`Application\Rehearsal`,
  `RehearsalAttendance`, `Timetable`, `TimetableItem`,
  `ScheduleComment`) were rewritten this phase to depend on
  `ProductionContextContract`/`IdentityContract`/`AuthorizationContract`/
  `MembershipContract` instead of
  `ProductionRepositoryInterface`/`ProductionAuthorizationService`/
  `ParticipantRepositoryInterface` directly. A UseCase now receives a
  `ProductionId` and resolves what it needs through a Contract, never a
  `Production`/`Person` Entity passed around internally.
- `TimetableItemTargetValidator` migrated from
  `ParticipantRepositoryInterface` to `MembershipContract`.
- `PublishTimetableVersionUseCase` additionally calls
  `NotificationContract::notify()` once per active Production member -
  the concrete "at least one existing notification migrated through the
  Contract path" demonstration (§14).
- Two Architecture Tests prove the boundary:
  `RehearsalModuleDependencyDirectionTest` (no concrete
  `Infrastructure\*` import) and `ModuleDependencyDirectionTest` (no
  direct import of Core's Repository interfaces/Entities - §4/§8).
- `RehearsalModuleContractIsolationTest` proves
  `CreateRehearsalUseCase`'s business logic runs correctly with **zero**
  real Core Repository - every Core-facing Contract is a hand-written
  Fake (`tests/Support/Fake*Contract.php`).

**Phase 3 additions**: `GetProductionPrintViewUseCase`/
`ListProductionTimetableItemsUseCase` (Rehearsal-owned per their REST
Controllers, but missed by Phase 2's own 24-UseCase count) migrated to
the same Contract pattern. All 26 UseCases + 7 REST Controllers'
construction consolidated into `RehearsalModuleBootstrap`
(`plugin/src/Rehearsal/`), whose entire constructor is Core Contracts +
Rehearsal's own Repository interfaces - no concrete
`Infrastructure\WordPress\*` class, no Core-internal type.
`RehearsalInstaller` (same directory) owns Rehearsal's 7 tables'
migration, extracted verbatim from Core's own installer.
`RehearsalModuleBootstrapIsolationTest` proves the full Bootstrap-wired
object graph (REST Controller -> UseCase -> Repository) works using
only Fakes. See `docs/architecture/WordPressPluginModuleBoundary.md`
for the complete detail.

---

# 7. Ticket Module

Not implemented. `docs/modules/Ticket.md` is the Module Template (§16),
updated this phase to reflect the now-concrete Contract shapes
(`getProductionOrganizationId()`, `isProductionMember()`,
`NotificationContract`'s real Adapter) and to point at the Rehearsal/
Accounting Modules' actual Phase 2 code as the pattern a real Ticket
implementation should follow - responsibility, intended Core Contract
usage, intended owned Domain/Database, API/Authorization/Notification/
WordPress-Adapter shape are documented; no Ticket code is added this
phase.

---

# 8. Accounting Module (Contract-adopted, one disclosed exception)

See `docs/modules/Accounting.md` for full detail. Summary:

- All 13 UseCases that previously depended on
  `ProductionRepositoryInterface`/`ProductionAuthorizationService`
  directly (`Budget\{Activate,Create,Get,List,Update}BudgetUseCase`,
  `Expense\{Confirm,Create,Get,List,Update}ExpenseUseCase`,
  `JournalEntry\{List,Post}JournalEntryUseCase`,
  `ProductionAccounting\GetProductionAccountingSummaryUseCase`) were
  migrated to `ProductionContextContract`/`IdentityContract`/
  `AuthorizationContract`/`MembershipContract`, mirroring the Rehearsal
  Module's own pattern exactly.
- ~~One disclosed, intentional exception~~ **closed in Phase 3**:
  `PostJournalEntryUseCase`'s Organization-Scope branch now calls
  `AuthorizationContract::canForOrganization()` (new this phase, a
  generic Core-owned Organization-Scope Capability method - see §11 of
  `docs/architecture/WordPressPluginModuleBoundary.md`) instead of
  `OrganizationAuthorizationService::hasRole()` directly. Accounting has
  zero remaining direct Core Application-service dependencies.
- `BudgetModuleContractIsolationTest` proves `CreateBudgetUseCase`'s
  business logic runs correctly with **zero** real Core Repository,
  mirroring `RehearsalModuleContractIsolationTest`.
- `ModuleDependencyDirectionTest::test_accounting_module_never_imports_core_internal_classes`
  covers the same denylist as Rehearsal's (§6), scoped to
  `Application/Account`, `Budget`, `Expense`, `JournalEntry`,
  `ProductionAccounting` and their `Domain` counterparts.
  `ModuleBoundaryDependencyTest` (Phase 3) additionally confirms
  Accounting never imports Rehearsal's namespaces and vice versa.
- **Not built this phase** (explicitly out of scope): an
  `AccountingModuleBootstrap`/`AccountingInstaller`/
  `AccountingModuleDescriptor` mirroring Rehearsal's Phase 3 work - see
  `docs/architecture/WordPressPluginModuleBoundary.md` §10 for the
  evaluated, concrete next steps.

---

# 9. Database Ownership

No table was renamed or added this phase. This section records which
Module owns which table's schema and business rules - Core does not
query a Module's tables directly, and a Module does not query another
Module's tables directly (all cross-Module data flows through Core
Contracts). Re-confirmed unchanged by the Phase 2 Contract migration -
Contract-ification moved *which class* a UseCase calls, not which
table it reads/writes.

| Table | Owner |
|---|---|
| `stageart_people`, `stageart_user_accounts`, `stageart_email_credentials`, `stageart_external_identities`, `stageart_email_verification_tokens`, `stageart_password_reset_tokens`, `stageart_refresh_tokens` | Core (Identity) |
| `stageart_organizations`, `stageart_organization_follows` | Core (Organization) |
| `stageart_productions`, `stageart_projects` | Core (Production Context) |
| `stageart_memberships`, `stageart_participants`, `stageart_production_delegates` | Core (Membership) |
| `stageart_join_keys` | Core (Join) |
| `stageart_favorites` | Core (Public Identity / Favorite) |
| `stageart_notification_read_states`, `stageart_push_preferences` | Core (Notification) |
| `stageart_rehearsals`, `stageart_rehearsal_attendances`, `stageart_timetables`, `stageart_timetable_items`, `stageart_timetable_item_participants`, `stageart_schedule_comments`, `stageart_timetable_version_published_notifications` | **Rehearsal Module** (last table shared-with-Core, disclosed - §14) |
| `stageart_budgets`, `stageart_budget_lines`, `stageart_expenses`, `stageart_expense_lines`, `stageart_accounts`, `stageart_journal_entries`, `stageart_journal_entry_lines` | **Accounting Module** |
| *(none yet)* | Ticket Module |

---

# 10. API Ownership

No route was renamed, moved, or had its business logic relocated into
a Controller this phase. Every migrated UseCase is still invoked from
its existing REST Controller exactly as before - the Controller calls
the Module's Application Service/UseCase, which internally now resolves
Core state via Contracts instead of Core Repositories; the Controller
layer itself required no change. Existing routes remain flat under
`stageart/v1/...`, not literally prefixed `/rehearsal/`/`/accounting/`
(`03-ModularArchitecture.md` §10 explicitly does not require breaking
existing routes retroactively).

| REST Controller (`plugin/src/Presentation/Rest/`) | Owner |
|---|---|
| `AuthenticationRestController`, `MeRestController`, `UserAccountRestController` | Core (Identity) |
| `OrganizationRestController`, `ProjectRestController` | Core (Organization) |
| `ProductionRestController`, `ProductionDelegateRestController` | Core (Production Context) |
| `MembershipRestController`, `ParticipantRestController`, `ParticipationRequestRestController` | Core (Membership) |
| `JoinKeyRestController` | Core (Join) |
| `FavoriteRestController` | Core (Public Identity) |
| `NotificationRestController`, `PushPreferenceRestController` | Core (Notification) |
| `DashboardRestController` | Core (aggregation across Core + Modules for Home) |
| `RehearsalRestController`, `RehearsalAttendanceRestController`, `TimetableRestController`, `TimetableItemRestController`, `TimetableVersionRestController`, `ScheduleCommentRestController`, `PrintViewRestController` | **Rehearsal Module** |
| `AccountRestController`, `BudgetRestController`, `ExpenseRestController`, `JournalEntryRestController`, `ProductionAccountingRestController` | **Accounting Module** |
| *(none yet)* | Ticket Module |

---

# 11. Frontend feature ownership

`mobile-rn/src/features/` is **not touched** this phase - Phase 2 is
Backend Architecture only, and no API request/response shape changed
(§13), so no Frontend change was needed or made. Ownership mapping
unchanged from Phase 1:

| `features/` subfolder | Owner |
|---|---|
| `auth`, `person`, `organization`, `production`, `membership`, `participation`, `joinKey`, `favorite`, `notifications`, `pushPreference`, `dashboard`, `mypage` | Core |
| `attendance`, `schedule`, `printView` | **Rehearsal Module** |
| `accounting` | **Accounting Module** |
| *(none yet)* | Ticket Module |

---

# 12. WordPress Plugin Portability / Future Adapter

Unchanged policy from `03-ModularArchitecture.md` §7 - Phase 2 widens
which code the mechanism actually protects, not the mechanism itself:

```text
Rehearsal / Accounting Module
      │
Core Contract (interface)
      │
 ┌────┴────┐
 │         │
CoreAuthorizationAdapter   (future) WordPressAdapter
CoreMembershipAdapter      implementing the same
CoreProductionContextAdapter  Contracts against a
CoreIdentityAdapter          different host
CoreOrganizationContextAdapter
CoreNotificationAdapter
```

No WordPress Plugin extraction was attempted this phase (out of
scope). What is now true: the Rehearsal Module's full 24-UseCase
dependency set, and the Accounting Module's 13 migrated UseCases, would
work unchanged if a future `WordPressAdapter` implementing
`IdentityContract`/`ProductionContextContract`/`MembershipContract`/
`AuthorizationContract`/`NotificationContract` were substituted for the
`Core*Adapter` classes - no Module Application code references a
concrete Adapter class, only the interfaces. This is no longer just an
architectural claim: `RehearsalModuleContractIsolationTest` and
`BudgetModuleContractIsolationTest` prove it by running real UseCases
against hand-written Fakes instead of any Core Adapter at all.

---

# 13. API Policy

Confirmed unchanged from `03-ModularArchitecture.md` §10: no existing
route was renamed, moved, or had its request/response shape changed by
this phase - the migration was entirely internal to each UseCase's own
constructor and body. No Frontend (`mobile-rn/`) file was touched.

---

# 14. Known remaining coupling (disclosed, not fixed this phase)

1. **`TimetableVersionPublishedNotification`** stays in Core's own
   `Domain\Notification` namespace - reconsidered explicitly this
   phase, not left in place merely because moving it was risky (Phase
   1's reasoning, which this phase's own instructions called
   insufficient). The actual reason: Core's **own** cross-cutting
   Notification Feed
   (`Application\Notification\ListNotificationsForProductionUseCase`,
   `MarkNotificationReadUseCase`, and
   `Application\Dashboard\GetMyDashboardUseCase`'s notification
   section) queries `TimetableVersionPublishedNotificationRepositoryInterface`
   directly. Moving the Entity into the Rehearsal Module's namespace
   would make **Core's own UseCases** depend on a Module's concrete
   Domain class - the opposite of the intended dependency direction.
   `PublishTimetableVersionUseCase` (Rehearsal's own Application code)
   still creates/saves this Entity directly - legitimate, since it is
   Rehearsal's own code creating a Core-owned, disclosed-shared Fact
   type - and additionally now calls `NotificationContract::notify()`
   for each active Production member, which is the real migration this
   phase delivers. The larger fix (a multi-producer Notification Fact
   abstraction every Module could implement, so Core's feed does not
   hardcode this one Entity type) is a bigger refactor, explicitly not
   attempted this phase.
2. **`NotificationContract` has a real Adapter now**
   (`CoreNotificationAdapter` → `Application\Notification\
   NotificationDispatcherInterface` → `Infrastructure\WordPress\
   Notification\WordPressNotificationDispatcher`, which fires a real
   `do_action('stageart_notification', $personId, $type, $payload)`
   WordPress Action Hook). Disclosed honestly: **zero listeners are
   registered on this hook yet** - it is a real, working extension
   point, not a UI-visible delivery mechanism. Building an actual
   listener (push notification, email, in-app feed row) for
   `timetable_version_published` is a follow-up, not attempted this
   phase per the explicit "don't build a lot of new notification
   functionality this round" instruction.
3. ~~`PostJournalEntryUseCase`'s Organization-Scope branch depends on
   `OrganizationAuthorizationService` directly~~ - **closed in Phase
   3**: `AuthorizationContract::canForOrganization()` (a new, generic,
   Core-owned Organization-Scope Capability method, not Accounting-
   specific) replaced it. See `docs/architecture/
   WordPressPluginModuleBoundary.md` §11.
4. **`OrganizationContextContract` has no current caller.** Defined in
   Phase 1 for a future Organization-scoped Module need; still unused -
   `organizationExists()` was never actually called by Rehearsal or
   Accounting through Phase 3 either.
5. **(Phase 3 finding) Core's own cross-Module aggregation UseCases
   read Rehearsal's Repository interfaces directly** -
   `GetMyDashboardUseCase`, `ListNotificationsForProductionUseCase`,
   `MarkNotificationReadUseCase` import
   `RehearsalRepositoryInterface`/`RehearsalAttendanceRepositoryInterface`/
   `TimetableVersionPublishedNotificationRepositoryInterface` directly
   - a Core -> Module Domain dependency, the reverse direction from
   everything else this phase closed. Disclosed by Phase 3's own
   re-investigation, not fixed - see `docs/architecture/
   WordPressPluginModuleBoundary.md` §14 for why (needs a read-model
   Contract abstraction, real design work with no other caller yet).
6. **No per-Module schema version exists** - `RehearsalInstaller` is
   still gated by Core's one shared `SchemaUpgrader::CURRENT_VERSION`,
   not its own. Phase 3 split *where* the SQL lives, not the
   versioning mechanism around it.
7. **Accounting's own `RehearsalModuleBootstrap`-equivalent is
   designed but not built** - `docs/architecture/
   WordPressPluginModuleBoundary.md` §10 lays out the concrete next
   steps; explicitly out of Phase 3's scope per its own governing
   instruction.

---

# 15. Implementation Rule for Claude / Future Developers

Before adding a new Rehearsal/Ticket/Accounting feature, or starting a
new Module:

1. Does this belong in Core, or in a Module? (§3 - if it requires
   knowing what a Rehearsal/Ticket/Accounting entry *is*, it's a
   Module.)
2. Does a Core Contract already cover what this needs from Core? If
   not and it's a genuine, immediate need, add a narrow one (§4) rather
   than reaching into Core's concrete Domain/Infrastructure. Never
   rename a Core Repository interface as a Module-side Contract with an
   identical shape - a Contract expresses what the Module needs, not a
   copy of Core's own persistence shape.
3. If this needs an Authorization check, does it belong to an existing
   Capability, or does it need a new one owned by the Module itself
   (§5)? Never add a Module-named method to
   `ProductionAuthorizationService`/`AuthorizationContract` again.
4. Which table(s) does this data belong to, and does that match the
   Module Ownership table (§9)? A Module should not read/write another
   Module's tables directly.
5. Can this UseCase's business logic be tested with Fake Contracts and
   no real Core Repository (§6/§8)? If not, something is still coupled
   too tightly to Core's concrete implementation.
6. Update `docs/modules/<Module>.md` and this file's Ownership tables
   when the answer changes.

---

# 16. Module Template

Every `docs/modules/<Module>.md` should record, at minimum (this is
what `Rehearsal.md`/`Ticket.md`/`Accounting.md` now each do):

- Status (implemented / partially / not implemented)
- Responsibility (with a Blueprint doc citation)
- Core Contract usage - which Contracts, and whether actually adopted
  in code (with call-site evidence), or design-intent only
- Entities/Domain the Module owns
- Database tables the Module owns
- API Ownership (which REST controllers)
- Authorization (Capability names it owns)
- Notification usage (if any), and whether it goes through
  `NotificationContract`
- Future WordPress Adapter note
- Known coupling / open items, disclosed explicitly rather than implied
  complete
- (Phase 3, once a Module has one) its own `ModuleDescriptor` and
  `*ModuleBootstrap` - see `docs/architecture/
  WordPressPluginModuleBoundary.md` §3/§4

---

# 17. Final Policy

> StageArt Core provides Identity, Organization, Production Context,
> Membership, a generic Capability-based Authorization mechanism
> (Production-Scope and, as of Phase 3, Organization-Scope), and
> Notification dispatch - never a Module-named authorization method.
> Rehearsal, Ticket, and Accounting are independent Domain Modules that
> consume Core through `StageArt\Core\Contract\*` interfaces, each
> owning its own Domain, Database tables, and Capability vocabulary.
> The Rehearsal Module fully demonstrates this pattern - every UseCase
> Contract-adopted, an Architecture Test enforcing the dependency
> direction in both directions (Module -> Core and sibling Module ->
> sibling Module), a Fake-Contract isolation test proving Core
> Repository independence, and, as of Phase 3, its own
> `RehearsalModuleBootstrap`/`RehearsalInstaller`/
> `RehearsalModuleDescriptor` consolidating its entire wiring into a
> form a genuinely separate WordPress Plugin could register - proven by
> a Bootstrap Isolation Test, not just claimed. The Accounting Module
> adopts the same Contract pattern with zero remaining disclosed
> exceptions, but has not yet had its own Bootstrap/Installer/Descriptor
> built (§8, a scoped-out next step, not a gap). Ticket has a defined
> boundary, including the Bootstrap/Installer/Descriptor shape to build
> against, but is not yet implemented.
