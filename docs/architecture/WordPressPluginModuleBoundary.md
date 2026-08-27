# WordPress Plugin Module Boundary (Phase 3)

Status: Confirmed - this document is the concrete answer to "can the
Rehearsal Module be extracted into its own WordPress Plugin". It is not
a plan to actually perform that split; StageArt ships as one Plugin
today and continues to. What changed this phase is that the boundary
between "StageArt Core Plugin" and "StageArt Rehearsal Plugin" now
exists in real code (`RehearsalModuleBootstrap`, `RehearsalInstaller`,
`RehearsalModuleDescriptor`), not only in `docs/architecture/
CoreModuleArchitecture.md`'s prose.

---

# 1. Investigation: what actually exists (re-read from current `main`, not assumed)

Phase 1/2 established `StageArt\Core\Contract\*` / `StageArt\Core\Adapter\*`
as real, tested classes under `plugin/src/Core/`. This phase re-read the
actual code before designing anything further, rather than trusting the
Phase 2 completion report at face value. Two findings mattered:

**`src/Rehearsal/` did not exist before this phase.** The instruction's
own vocabulary (`src/Core/`, `src/Rehearsal/`) describes a target
shape, not the pre-Phase-3 reality: Rehearsal's actual code was (and
mostly still is) organized by DDD layer, not by Module -
`Domain/Rehearsal`, `Domain/RehearsalAttendance`, `Domain/Timetable`,
`Domain/TimetableItem`, `Domain/ScheduleComment` (Entities/Repository
interfaces), matching `Application/*` counterparts, plus
`Infrastructure/WordPress/Persistence/WordPressRehearsal*Repository`
and `Presentation/Rest/Rehearsal*Controller` /
`Presentation/Rest/Timetable*Controller` / `Presentation/Rest/
ScheduleCommentRestController` / `Presentation/Rest/PrintViewRestController`.
This phase did **not** relocate that layered code into a `Rehearsal/`
package (a large, high-risk rewrite with no architectural benefit -
`03-ModularArchitecture.md` already treats DDD-layer-then-Module-
namespace as an acceptable shape). What moved into a new
`plugin/src/Rehearsal/` directory is a thin **composition layer**:
`RehearsalModuleBootstrap` (wiring), `RehearsalModuleDescriptor`
(metadata), `RehearsalInstaller` (its own 7 tables' migration) - the
three things §4/§5/§6 of the governing instruction actually asked for.

**Two Rehearsal-owned UseCases were still Core-Repository-coupled.**
`Application\PrintView\GetProductionPrintViewUseCase` and
`Application\ProductionSchedule\ListProductionTimetableItemsUseCase`
back `PrintViewRestController`/part of `TimetableRestController` - both
already classified as Rehearsal-owned in Phase 2's own API Ownership
table - but both still depended on `ProductionRepositoryInterface`/
`ProductionAuthorizationService` directly, an inconsistency Phase 2's
own 24-UseCase count missed. Migrated this phase (§3 below) so the
Module's Contract-adoption claim is actually true for everything it
owns, not just the files Phase 2 happened to enumerate.

## A/B/C classification

**A. Rehearsal owns directly** (unchanged in shape, reconfirmed):
Domain Entities (`Rehearsal`, `RehearsalAttendance`, `Timetable`,
`TimetableItem`, `ScheduleComment`), their Repository interfaces and
`Infrastructure\WordPress\Persistence` implementations, 26 Application
UseCases (24 from Phase 2 + the 2 found above), 7 REST Controllers, 7
DB tables (§6), `RehearsalCapability`, the
`timetable_version_published` Notification request (via
`NotificationContract`), and now its own `RehearsalModuleBootstrap`/
`RehearsalModuleDescriptor`/`RehearsalInstaller`.

**B. Rehearsal gets from Core**: `IdentityContract`,
`ProductionContextContract` (incl. this phase's confirmation that
`getProductionOrganizationId()` is never called by Rehearsal - only
Accounting needs it), `MembershipContract`, `AuthorizationContract`,
`NotificationContract`. `OrganizationContextContract` is defined but
still has zero callers anywhere, Rehearsal included.

**C. WordPress-dependent**: REST registration (`register_rest_route`
via each Controller's `register_routes()`, called from
`add_action('rest_api_init', ...)`), DB access (`$wpdb`, `dbDelta()`),
the Plugin Activation hook (`register_activation_hook` →
`SchemaUpgrader::maybeUpgrade()` → `Installer::install()` →
`RehearsalInstaller::install()`), and `plugins_loaded` (→
`Plugin::boot()`). None of this is Rehearsal-specific machinery; it is
the same WordPress integration surface every table/route in the
project uses.

---

# 2. Rehearsal Module Package Boundary (formal definition)

```text
StageArt Rehearsal Module

Owned by Rehearsal
├ Domain            Domain/{Rehearsal,RehearsalAttendance,Timetable,TimetableItem,ScheduleComment}/
├ Application        Application/{Rehearsal,RehearsalAttendance,Timetable,TimetableItem,ScheduleComment,PrintView,ProductionSchedule}/
├ Infrastructure      Infrastructure/WordPress/Persistence/WordPressRehearsal*/WordPressTimetable*/WordPressScheduleComment*
├ REST/API            Presentation/Rest/{Rehearsal,RehearsalAttendance,ScheduleComment,Timetable,TimetableVersion,TimetableItem,PrintView}RestController
├ Database            RehearsalInstaller (7 tables, §6)
├ Capability          Application/Rehearsal/RehearsalCapability
├ Notification Request PublishTimetableVersionUseCase -> NotificationContract::notify()
└ Module Registration RehearsalModuleDescriptor + RehearsalModuleBootstrap

Depends on Core Contracts (plugin/src/Core/Contract/)
├ IdentityContract
├ ProductionContextContract
├ MembershipContract
├ AuthorizationContract
└ NotificationContract
```

The Rehearsal Module never imports `ProductionRepositoryInterface`,
`ParticipantRepositoryInterface`, `PersonRepositoryInterface`, the
`Production`/`Participant`/`Person` Domain Entities, or
`ProductionAuthorizationService` - enforced by
`tests/Core/ModuleDependencyDirectionTest.php` and
`tests/Core/ModuleBoundaryDependencyTest.php` (§8). It also never
imports Accounting's namespaces, and Core's own `src/Core/` never
imports Rehearsal's - both directions checked, not just the one Phase 2
tested.

---

# 3. Module Registration

`plugin/src/Core/Module/ModuleDescriptor.php` (interface) +
`ModuleRegistry.php` (plain collection) - deliberately minimal, **not**
a Plugin Loader: no autodiscovery, no dependency resolution, no
activation-state management. A Module declares:

```php
interface ModuleDescriptor
{
    public function moduleId(): string;
    public function version(): string;
    public function requiredCoreVersion(): string;
    /** @return class-string[] */
    public function requiredContracts(): array;
    /** @return string[] */
    public function ownedTables(): array;
}
```

`RehearsalModuleDescriptor` (`plugin/src/Rehearsal/`) implements this:
`moduleId() = 'rehearsal'`, its own `version()` independent of Core's
`STAGEART_VERSION`, the 5 Contracts from §2, and its 7 owned tables.
`requiredContracts()` is not just declared and forgotten -
`ModuleDependencyDirectionTest` cross-checks that Rehearsal's real code
never reaches past this declared list.

`ModuleRegistry` is what makes §11 ("Coreだけでも動作する") testable: a
Core-only boot path never has to construct a `ModuleDescriptor` or a
Bootstrap at all, and `CoreOnlyBootstrapTest` proves Core's own Contract
Adapters function with the Registry left empty.

---

# 4. RehearsalModuleBootstrap

`plugin/src/Rehearsal/RehearsalModuleBootstrap.php` consolidates every
piece of Rehearsal's own wiring - UseCase construction (26 UseCases)
and REST Controller construction (7 Controllers) - into one class whose
entire constructor signature is Core Contracts + Rehearsal's own
Repository *interfaces* + `TransactionManagerInterface`:

```php
public function __construct(
    RehearsalRepositoryInterface $rehearsals,
    RehearsalAttendanceRepositoryInterface $rehearsalAttendances,
    ScheduleCommentRepositoryInterface $scheduleComments,
    TimetableRepositoryInterface $timetables,
    TimetableItemRepositoryInterface $timetableItems,
    TimetableVersionPublishedNotificationRepositoryInterface $timetableVersionPublishedNotifications,
    ProductionContextContract $productionContext,
    IdentityContract $identity,
    AuthorizationContract $authorization,
    MembershipContract $membership,
    NotificationContract $notification,
    TransactionManagerInterface $transactions
)
```

Not a single argument is a concrete `Infrastructure\WordPress\*` class
or a Core-internal type. `Presentation\Plugin::boot()` today builds the
concrete `WordPress*Repository` instances and the `Core*Adapter`
Contract implementations, then hands them to this constructor - but
nothing about `RehearsalModuleBootstrap`'s own shape assumes that
caller. A future, genuinely separate StageArt Rehearsal Plugin's own
activation entry point could construct and call it identically, passing
its own Repository implementations (still satisfying the same
interfaces) and whatever Contract Adapters that future world uses
(`Core*Adapter` today; a `WordPressAdapter` implementing the same
Contracts against a different host, per §12 of
`CoreModuleArchitecture.md`, tomorrow).

`restControllers(): array` is the only public method - 7 Controllers,
each with the existing `register_routes()` convention, registered
identically to every other Controller in `Plugin.php`:

```php
foreach ($rehearsalModule->restControllers() as $rehearsalRestController) {
    add_action('rest_api_init', [$rehearsalRestController, 'register_routes']);
}
```

Route paths and methods are byte-identical to before this refactor -
confirmed live (§9).

**Deliberately excluded**: `GetMyDashboardUseCase`,
`ListNotificationsForProductionUseCase`, `MarkNotificationReadUseCase`
- Core's own cross-Module aggregation UseCases, which read Rehearsal's
Repository interfaces directly. Including them in the Bootstrap would
misrepresent them as Rehearsal-owned; they stay in `Plugin.php` as
Core's own wiring. See §10 for why this is a disclosed gap, not a fixed
one.

---

# 5. Activation / Migration Boundary

`RehearsalInstaller::install($wpdb, $charsetCollate)`
(`plugin/src/Rehearsal/`) owns the `CREATE TABLE`/`ALTER TABLE`
statements for Rehearsal's 7 tables - moved verbatim (byte-identical
SQL) out of the previously-monolithic
`Infrastructure\WordPress\Schema\Installer::install()`, which now
delegates to it:

```php
// Installer::install() (Core's own, now):
RehearsalInstaller::install($wpdb, $charsetCollate);
```

The single existing version-gate
(`SchemaUpgrader::maybeUpgrade()`/`CURRENT_VERSION`) is unchanged -
this phase did not introduce per-Module schema versions (real design
work with no other caller yet, deliberately out of scope). The
conceptual future flow this makes possible:

```text
Plugin Activation
       │
Core presence/version check  (ModuleDescriptor::requiredCoreVersion())
       │
Contract availability check  (ModuleDescriptor::requiredContracts())
       │
Rehearsal Migration           RehearsalInstaller::install()
       │
REST/API registration         RehearsalModuleBootstrap::restControllers()
```

Only the last two steps are exercised by real code today (inside
`Installer::install()` and `Plugin::boot()` respectively) - the first
two are named in `ModuleDescriptor` but not yet enforced by any runtime
check, since there is only one Core version to check against right now.
Declaring the shape now is what lets that check be added later without
every Module's own metadata changing shape again.

**Verified live, not just unit-tested** (`plugin/tests/` never exercises
`Installer.php` at all, since it uses in-memory Repositories): the
refactored `Installer::install()` was invoked directly against the real
ConoHa dev database via WP-CLI. Before: 3 existing `stageart_rehearsals`
rows. After: still 3 rows, `stageart_timetable_version_published_notifications`
still exists, `stageart_timetables`' `rehearsal_version` unique index
and `rehearsal_id` key both intact. `dbDelta()`'s idempotency, not a
schema change, is what made this safe - no SQL text differs from before
the extraction.

---

# 6. Database Ownership (Rehearsal, finalized)

| Table | Owner | Migration |
|---|---|---|
| `stageart_rehearsals` | Rehearsal | `RehearsalInstaller` |
| `stageart_rehearsal_attendances` | Rehearsal | `RehearsalInstaller` |
| `stageart_schedule_comments` | Rehearsal | `RehearsalInstaller` |
| `stageart_timetables` | Rehearsal | `RehearsalInstaller` |
| `stageart_timetable_items` | Rehearsal | `RehearsalInstaller` |
| `stageart_timetable_item_participants` | Rehearsal | `RehearsalInstaller` |
| `stageart_timetable_version_published_notifications` | Rehearsal (physical), Core (a disclosed reader - §10) | `RehearsalInstaller` |

| Item | Owner |
|---|---|
| Table Creation | Rehearsal (`RehearsalInstaller`) |
| Migration | Rehearsal (`RehearsalInstaller`, called from Core's `Installer::install()`) |
| Schema Upgrade | Core's existing single version-gate (`SchemaUpgrader`) - not yet per-Module |
| Data Access | Rehearsal's own `Infrastructure\WordPress\Persistence\WordPressRehearsal*`/`WordPressTimetable*`/`WordPressScheduleComment*` Repositories |
| Core Dependency | `ProductionId` (a Value Object reference) only - no foreign key, no Production row read directly |

No Core table was renamed, added, or dropped. No Rehearsal table's SQL
changed - this section records ownership of already-existing tables,
not a migration of data.

---

# 7. REST API Ownership (Rehearsal, finalized)

| Route (unchanged) | Controller | Owner |
|---|---|---|
| `/rehearsals`, `/rehearsals/{id}`, `/rehearsals/{id}/{confirm,activate,complete,cancel}`, `/productions/{id}/rehearsals` | `RehearsalRestController` | Rehearsal |
| `/rehearsals/{id}/attendances`, `/rehearsal-attendances/{id}`, `/{id}/respond`, `/{id}/record-actual-status` | `RehearsalAttendanceRestController` | Rehearsal |
| `/rehearsals/{id}/schedule-comments`, `/timetable-items/{id}/schedule-comments`, `/schedule-comments/{id}` | `ScheduleCommentRestController` | Rehearsal |
| `/rehearsals/{id}/timetable`, `/timetable/draft`, `/timetable-items`, `/timetable-items/draft`, `/productions/{id}/timetable-items` | `TimetableRestController` | Rehearsal |
| `/rehearsals/{id}/timetable-versions`, `/timetable-versions/{id}/publish` | `TimetableVersionRestController` | Rehearsal |
| `/timetable-items/{id}` | `TimetableItemRestController` | Rehearsal |
| `/productions/{id}/timetable/print` | `PrintViewRestController` | Rehearsal |

**Verified live** (not just read from source): `do_action('rest_api_init')`
was triggered against the real ConoHa dev environment after this
phase's refactor, and `$wp_rest_server->get_routes()` was inspected
directly - all 22 routes above are present, unchanged, with the same
regex patterns as before. Future physical split: these routes stay
exactly as-is; only which Plugin's `rest_api_init` hook registers them
changes.

---

# 8. Architecture Tests (Module boundary, extended this phase)

`tests/Core/ModuleDependencyDirectionTest.php` (Phase 2): Module -> Core
Internals - NG.

`tests/Core/ModuleBoundaryDependencyTest.php` (new this phase):

| Direction | Result |
|---|---|
| Rehearsal -> Core Contract | OK (existing positive check, Phase 2) |
| Rehearsal -> Core Internal Repository/Entity | NG (existing, Phase 2) |
| Rehearsal -> Accounting | **NG (new)** |
| Accounting -> Core Contract | OK (**new** positive check) |
| Accounting -> Rehearsal | **NG (new)** |
| `src/Core/` -> Rehearsal Domain | **NG (new)** |
| `src/Core/` -> Accounting Domain | **NG (new)** |

All scan real `use` statements via regex against each file's own import
list, not a whole-file substring search - a docblock mentioning another
Module's class name in prose does not trip these.

**Scoping disclosure**: the `src/Core/` -> Module Domain check is
scoped to `plugin/src/Core/` specifically (the `Contract`/`Adapter`/
`Module` subdirectories - the literal boundary layer this whole
initiative built), not to "every file outside Rehearsal/Accounting's
own directories". Core's cross-Module aggregation UseCases
(`Application\Dashboard\GetMyDashboardUseCase`,
`Application\Notification\ListNotificationsForProductionUseCase`/
`MarkNotificationReadUseCase`) live outside `src/Core/` and still
import `RehearsalRepositoryInterface`/`RehearsalAttendanceRepositoryInterface`/
`TimetableVersionPublishedNotificationRepositoryInterface` directly - a
real, disclosed, NOT-yet-fixed coupling (§10), not silently hidden by
this test's scoping choice.

---

# 9. Plugin Boundary / Bootstrap Isolation Tests

`tests/Rehearsal/RehearsalModuleBootstrapIsolationTest.php` (new):
constructs `RehearsalModuleBootstrap` using hand-written Fakes for
every Core Contract (`FakeProductionContextContract`,
`FakeIdentityContract`, `FakeAuthorizationContract`,
`FakeMembershipContract`) and in-memory Fakes for every one of
Rehearsal's own Repository interfaces - **zero**
`Infrastructure\WordPress\*` class is imported anywhere in the test
file. Three assertions:

1. `restControllers()` returns exactly the 7 expected Controller types.
2. The real `CreateRehearsalUseCase` instance, pulled back out of the
   Bootstrap-constructed `RehearsalRestController` via Reflection, is
   executed directly and succeeds - proving the entire object graph the
   Bootstrap wires together works end to end, not just that the
   constructor didn't throw.
3. The same path denies a Person the Fake `AuthorizationContract` never
   granted (deny-by-default).

`tests/Core/CoreOnlyBootstrapTest.php` (new): the companion "Core
without any Module" proof - constructs `CoreIdentityAdapter`/
`CoreProductionContextAdapter` and exercises them successfully with an
empty `ModuleRegistry`, and separately confirms registering a
`RehearsalModuleDescriptor` is pure metadata (does not construct
`RehearsalModuleBootstrap` or touch any Repository).

---

# 10. Accounting Module Package Boundary

Investigated this phase (not assumed from Phase 2's report):

- **Owned Domain**: `Budget`/`BudgetLine`, `Expense`/`ExpenseLine`,
  `Account`, `JournalEntry`/`JournalEntryLine`
  (`Domain/{Budget,Expense,Account,JournalEntry}`).
- **Owned Tables**: `stageart_budgets`, `stageart_budget_lines`,
  `stageart_expenses`, `stageart_expense_lines`, `stageart_accounts`,
  `stageart_journal_entries`, `stageart_journal_entry_lines` - still
  created inside Core's monolithic `Installer::install()` (not split
  into an `AccountingInstaller` this phase - see below).
- **Owned REST API**: `AccountRestController`, `BudgetRestController`,
  `ExpenseRestController`, `JournalEntryRestController`,
  `ProductionAccountingRestController`.
- **Core Contracts**: all 13 previously-migrated UseCases (Phase 2)
  confirmed still Contract-based; unchanged this phase.
- **WordPress dependency**: identical shape to Rehearsal's (C above) -
  nothing Accounting-specific.
- **`OrganizationAuthorizationService` exception**: **closed this
  phase** (§11) - `PostJournalEntryUseCase`'s Organization-Scope branch
  now calls `AuthorizationContract::canForOrganization()` instead.
  Accounting has **zero** remaining direct Core Application-service
  dependencies.

**Can Accounting follow the same Bootstrap pattern?** Yes, structurally
- nothing found this phase blocks it. Not done this phase because the
governing instruction explicitly scoped full Bootstrap-level proof to
Rehearsal only ("今回はAccountingを完全にRehearsalと同じレベルまで実証する
必要はありません"). The concrete remaining steps for a future
`AccountingModuleBootstrap`/`AccountingInstaller`/
`AccountingModuleDescriptor`, in the same shape as Rehearsal's:

1. Extract Accounting's 7 tables' `CREATE TABLE` statements from
   `Installer::install()` into `AccountingInstaller`, called the same
   way `RehearsalInstaller` is.
2. Consolidate the 13 UseCases + 5 REST Controllers' construction from
   `Plugin.php` into `AccountingModuleBootstrap`, taking Core Contracts
   + Accounting's own Repository interfaces as its entire constructor.
3. Add `AccountingModuleDescriptor` (moduleId `'accounting'`, its own
   version, `requiredContracts()`, `ownedTables()`).
4. Add an `AccountingModuleBootstrapIsolationTest` mirroring
   `RehearsalModuleBootstrapIsolationTest`.

---

# 11. Organization-Scope Capability (new Core Contract this phase)

`PostJournalEntryUseCase`'s Organization-Scope branch (a JournalEntry
not tied to any Production) was the one disclosed exception Phase 2
left open: `AuthorizationContract` only covered Production-Scope
Capability checks, so this one branch still called
`OrganizationAuthorizationService::hasRole()` directly.

**Design decision**: added `AuthorizationContract::canForOrganization(
PersonId $personId, OrganizationId $organizationId, string $capability
): bool` - deliberately generic (Capability-string-based, mirroring
`canForProduction()`'s own shape), not Accounting-specific, per the
governing instruction's explicit requirement ("Accounting専用ではなく、
Core全体で汎用的に使える形に"). `Core\Contract\OrganizationCapability::OWNER
= 'Organization.Owner'` is the one recognized capability string today,
owned by Core (an Organization-level "is this Person the Owner" check
is inherently a Core concept, not something any one Module's domain
defines) - any future Module needing an Organization-Scope check reuses
this same Contract method, not a new one.

Implementation: `CoreAuthorizationAdapter::canForOrganization()` maps
the capability string to the `RoleKey`s that satisfy it (`OWNER` today)
and delegates to a new thin pass-through,
`ProductionAuthorizationService::hasOrganizationRole()` (itself just
forwarding to the already-injected `OrganizationAuthorizationService`,
mirroring how `resolveCurrentPerson()` already pass-throughs) - this
avoided adding a second Core Application service dependency to
`CoreAuthorizationAdapter`'s own constructor, so none of its 14
existing call sites (13 test files + `Plugin.php`) needed updating.

`PostJournalEntryUseCase` was rewritten to depend only on
`ProductionContextContract`/`IdentityContract`/`AuthorizationContract` -
`OrganizationAuthorizationService`/`RoleKey` are no longer imported
anywhere in the Accounting Module's Application code.

---

# 12. Ticket Module Template (updated, not implemented)

`docs/modules/Ticket.md` was updated to reflect this phase's now-real
Contract shapes (`getProductionOrganizationId()`,
`canForOrganization()`, the real `NotificationContract` Adapter) and to
point at Rehearsal's/Accounting's actual Phase 3 code as the concrete
pattern to follow, including the Architecture Test / Bootstrap
Isolation Test shape a real Ticket implementation should add. No Ticket
code is added this phase.

---

# 13. Core-only operation

`CoreOnlyBootstrapTest` (§9) proves Core's own Contract Adapters
function correctly with an empty `ModuleRegistry` and no
`RehearsalModuleBootstrap` ever constructed. This is the practical,
testable form of "if only the StageArt Core Plugin were installed,
Core itself would still work" - without attempting an actual physical
Plugin split, which remains explicitly out of this phase's scope.

---

# 14. Known Remaining Coupling (disclosed, not fixed this phase)

1. **Core's cross-Module aggregation UseCases still read Rehearsal's
   Repository interfaces directly.** `Application\Dashboard\
   GetMyDashboardUseCase`, `Application\Notification\
   ListNotificationsForProductionUseCase`/`MarkNotificationReadUseCase`
   import `RehearsalRepositoryInterface`/
   `RehearsalAttendanceRepositoryInterface`/
   `TimetableVersionPublishedNotificationRepositoryInterface` directly
   - a Core -> Module Domain dependency, the opposite direction from
   everything else this phase closed. Discovered by this phase's own
   re-investigation (§1), not previously disclosed. Not fixed this
   phase: a genuine fix needs a read-model/query abstraction each
   Module could implement and Core could aggregate over generically
   (e.g. a `DashboardContributorContract` every Module registers into),
   which is real design work with no other current caller - guessing at
   its shape now would risk exactly the kind of premature,
   un-validated Contract the governing instruction warns against. The
   `src/Core/` -> Module Domain Architecture Test (§8) is deliberately
   scoped to not (yet) cover this - scoping disclosed, not hidden.
2. **`OrganizationContextContract` still has zero callers.** Defined in
   Phase 1, still unused by Rehearsal, Accounting, or Core's own code.
3. **No per-Module schema version exists** - `RehearsalInstaller`/
   (a future) `AccountingInstaller` are both still gated by Core's one
   shared `SchemaUpgrader::CURRENT_VERSION`. A genuinely separate
   Plugin would need its own version-gated activation/upgrade path;
   this phase only split *where* the SQL lives, not the versioning
   mechanism around it.
4. **Accounting Bootstrap/Descriptor/Installer are designed but not
   built** (§10) - explicitly out of this phase's scope per the
   governing instruction.
