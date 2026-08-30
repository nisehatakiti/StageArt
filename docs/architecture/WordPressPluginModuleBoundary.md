# WordPress Plugin Module Boundary (Phase 3)

Status: Confirmed - this document is the concrete answer to "can the
Rehearsal and Accounting Modules be extracted into their own WordPress
Plugins". It is not a plan to actually perform that split; StageArt
ships as one Plugin today and continues to. What changed this phase is
that the boundary between "StageArt Core Plugin" and each Module's own
Plugin now exists in real code
(`RehearsalModuleBootstrap`/`RehearsalInstaller`/`RehearsalModuleDescriptor`,
`AccountingModuleBootstrap`/`AccountingInstaller`/`AccountingModuleDescriptor`),
not only in `docs/architecture/CoreModuleArchitecture.md`'s prose. §10
originally evaluated Accounting's boundary as "structurally feasible,
not built" - it has since been built, in the same session, following
exactly the concrete steps §10 laid out.

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

# 10. Accounting Module Package Boundary (now built, same session)

Investigated, then built - the four steps originally listed here as
future work were completed immediately after, once the pattern was
proven for Rehearsal:

- **Owned Domain**: `Budget`/`BudgetLine`, `Expense`/`ExpenseLine`,
  `Account`, `JournalEntry`/`JournalEntryLine`
  (`Domain/{Budget,Expense,Account,JournalEntry}`) - unchanged, DDD
  layering kept, mirroring Rehearsal's own approach (§1).
- **Owned Tables**: `stageart_accounts`, `stageart_budgets`,
  `stageart_budget_lines`, `stageart_journal_entries`,
  `stageart_journal_entry_lines`, `stageart_expenses`,
  `stageart_expense_lines` - extracted verbatim into
  `AccountingInstaller` (`plugin/src/Accounting/`), called from Core's
  `Installer::install()` exactly as `RehearsalInstaller` is. Verified
  live: `Installer::install()` invoked directly against the ConoHa dev
  DB, all 7 tables confirmed present afterward.
- **Owned REST API**: `AccountRestController`, `BudgetRestController`,
  `ExpenseRestController`, `JournalEntryRestController`,
  `ProductionAccountingRestController` - all 5 now constructed inside
  `AccountingModuleBootstrap`. Verified live: `rest_api_init` triggered
  against the real environment, all 10 Accounting routes present and
  byte-identical to before.
- **Core Contracts**: all 15 UseCases now Contract-based - the 13
  migrated in Phase 2, plus `CreateAccountUseCase`/`ListAccountsUseCase`
  (Account CRUD, found still depending on
  `OrganizationAuthorizationService`/`OrganizationRepositoryInterface`
  directly during this Bootstrap work - a gap Phase 2's own 13-file
  count missed, exactly as PrintView/ProductionSchedule was missed for
  Rehearsal). Migrating these two gave `OrganizationContextContract`
  its first real caller (previously unused by any Module since Phase
  1) and required a second Organization-Scope Capability,
  `OrganizationCapability::MEMBER` (any ACTIVE Membership, not just
  Owner) alongside the existing `OWNER`.
- **`AccountingModuleBootstrap`** (`plugin/src/Accounting/`) - all 15
  UseCases + 5 REST Controllers, constructor limited to Core Contracts
  (`ProductionContextContract`, `OrganizationContextContract`,
  `IdentityContract`, `AuthorizationContract`, `MembershipContract`) +
  Accounting's own 4 Repository interfaces + `TransactionManagerInterface`.
- **`AccountingModuleDescriptor`** - `moduleId() = 'accounting'`,
  `requiredContracts()` (the 5 Contracts above),
  `ownedTables()` (the 7 tables above).
- **`AccountingModuleBootstrapIsolationTest`** - mirrors
  `RehearsalModuleBootstrapIsolationTest` exactly: builds
  `AccountingModuleBootstrap` from Fakes only (including a new, minimal
  inline `FakeOrganizationContextContract` - the first Fake this
  Contract needed, since Rehearsal never called it), pulls
  `CreateBudgetUseCase` back out of the Bootstrap-constructed
  `BudgetRestController` via Reflection, executes it, and confirms both
  the success and deny-by-default paths.

Accounting now has **zero** remaining direct Core Application-service
dependencies (`OrganizationAuthorizationService`, `ProductionAuthorizationService`,
`ProductionRepositoryInterface`, `OrganizationRepositoryInterface`) -
the same standard Rehearsal meets.

**Phase 4 §2 audit** (re-verifying this section against the actual
code, not re-describing it from memory): found
`AccountingModuleDescriptor::requiredContracts()` had drifted out of
sync with `AccountingModuleBootstrap`'s real constructor - it was
missing `OrganizationContextContract`, even though the Bootstrap
already required it. Corrected by re-deriving the declared list from
the Bootstrap's actual constructor signature. Also re-investigated
"does any Core-owned code depend on Accounting's Domain/Application
classes directly" (the same question Phase 4 §1 answered "yes, one
case" for Rehearsal) - a full `use`-statement scan across `src/Core/`,
`Application/Dashboard`, `Application/Notification`, and every other
Core Application namespace found **zero** matches. No Provider-Contract
inversion is needed for Accounting; none was spectulatively built.
`plugin/src/Accounting/` and `plugin/src/Rehearsal/` are structurally
symmetric (`ModuleBootstrap`/`ModuleDescriptor`/`Installer`, no
Provider interface on either side) - not because symmetry was assumed,
but because both were independently investigated and neither currently
has a real Core-side consumer needing one.

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

1. ~~Core's cross-Module aggregation UseCases still read Rehearsal's
   Repository interfaces directly~~ - **closed in Phase 4 §1** (see §15
   below). The one genuine violation (`GetMyDashboardUseCase`'s direct
   `RehearsalRepositoryInterface`/`RehearsalAttendanceRepositoryInterface`
   dependency) is inverted via a Core-owned Port,
   `UpcomingRehearsalProviderInterface`. `ListNotificationsForProductionUseCase`/
   `MarkNotificationReadUseCase`'s `TimetableVersionPublishedNotificationRepositoryInterface`
   dependency was investigated and confirmed **not** a Module-Domain
   violation (that interface/Entity lives in Core's own
   `Domain\Notification` namespace by deliberate Phase 2 design) - those
   two UseCases were still migrated from
   `ProductionRepositoryInterface`/`ProductionAuthorizationService` to
   Core Contracts regardless, since they simply hadn't been brought up
   to the same standard as every other UseCase yet.
2. ~~`OrganizationContextContract` still has zero callers~~ - **closed**:
   `CreateAccountUseCase`/`ListAccountsUseCase` are now its first real
   callers (§10).
3. **No per-Module schema version exists** - `RehearsalInstaller` and
   `AccountingInstaller` are both still gated by Core's one shared
   `SchemaUpgrader::CURRENT_VERSION`. A genuinely separate Plugin would
   need its own version-gated activation/upgrade path; this phase only
   split *where* the SQL lives, not the versioning mechanism around it.
4. ~~Accounting Bootstrap/Descriptor/Installer are designed but not
   built~~ - **closed**: built in the same session immediately after
   this document's §10 first evaluated them as feasible (see §10's
   current text).

---

# 15. Core -> Rehearsal reverse dependency, closed (Phase 4 §1)

**The problem**: `GetMyDashboardUseCase` (Core's own
`Application\Dashboard`) directly imported and constructed against
`RehearsalRepositoryInterface`/`RehearsalAttendanceRepositoryInterface`
- Rehearsal's own Repository interfaces - to build its "upcoming
rehearsals" Dashboard section, plus `RehearsalStatus`/
`RehearsalAttendancePhase` Domain enums for filtering logic. This is
the exact reverse of every dependency direction Phases 1-3 spent their
effort enforcing: `Core -> Module Domain`, not `Module -> Core
Contract`.

**Design considered**: two shapes were on the table - (A) Core defines
a named-after-the-screen Contract (`RehearsalDashboardContract`), or
(B) a multi-producer "Contribution" registry
(`DashboardContributionContract`) multiple Modules could plug into.
Both were explicitly flagged as risking either premature naming
coupled to one screen, or over-engineering a Plugin Framework with only
one real producer today.

**What was built - simple dependency inversion**:

```php
// Core owns this interface (Application\Dashboard\UpcomingRehearsalProviderInterface) -
// Core is the consumer, so Core defines the Port. Named after what the
// Dashboard needs ("upcoming rehearsals"), never "Rehearsal Module".
interface UpcomingRehearsalProviderInterface
{
    /** @return UpcomingRehearsalResult[] */
    public function findUpcomingRehearsalsForPerson(PersonId $personId, DateTimeImmutable $now, int $limit): array;
}
```

`RehearsalUpcomingRehearsalProvider` (`plugin/src/Rehearsal/`)
implements it - holding the exact filtering/joining logic
`GetMyDashboardUseCase` used to contain directly (moved verbatim, not
reimplemented), since only Rehearsal has legitimate access to
`Rehearsal`/`RehearsalAttendance` Domain Entities.
`RehearsalModuleBootstrap::upcomingRehearsalProvider()` exposes the one
real instance; `Presentation\Plugin::boot()` passes it straight into
`GetMyDashboardUseCase`'s own constructor in place of the two
Repository interfaces. `UpcomingRehearsalResult` (the Dashboard-owned
DTO) was changed from a `fromDomain(RehearsalAttendance, Rehearsal,
Production)` factory to a primitive-only `create(...)` factory, so
Core's own namespace has zero references to Rehearsal's Domain classes
left, not even in a DTO builder.

`ProductionContextContract` gained a bulk `getProductions(ProductionId[]):
array<string, ProductionSummary>` method (mirroring the existing §29
N+1-avoidance convention already used on Repository `findByIds()`
methods) - without it, resolving each upcoming Rehearsal's Production
name one Contract call at a time would have reintroduced the N+1 query
pattern the original code's bulk `findByIds()` call already avoided.

**Deliberately not built**: a multi-producer registry, or a second
Contract for Ticket/other future Modules to plug into the Dashboard
before any of them exist yet. `docs/architecture/CoreModuleArchitecture.md`'s
own Implementation Rule (§15, item 2: "add a narrow one rather than
reaching into Core's concrete Domain/Infrastructure" - and, by the same
logic, rather than a speculative multi-Module framework) is followed
literally: exactly the interface today's one real caller needs, nothing
broader.

**Verified**: the entire existing `GetMyDashboardUseCaseTest.php` suite
(highly detailed - covers attendance-phase filtering, cross-Production
sorting, the 50-item cap, terminal-status exclusion) passed unchanged
after the logic transplant, proving behavior was preserved exactly, not
just that the code compiles. `ModuleBoundaryDependencyTest::test_core_never_imports_rehearsal_or_accounting_domain`
was widened to scan `Application/Dashboard`/`Application/Notification`
in addition to `src/Core/`, and passes - the concrete regression guard
for this fix.
