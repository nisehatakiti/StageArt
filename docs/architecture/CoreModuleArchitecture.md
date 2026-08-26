# StageArt Core / Module Architecture

Status: Confirmed - implements `docs/03-ModularArchitecture.md`'s
policy concretely, in code, for the first time (Rehearsal Module). This
document is the practical companion to that Blueprint: it records what
actually exists (files, classes, tables, routes), not just the intended
policy.

---

# 1. Purpose

`03-ModularArchitecture.md` established the policy: StageArt Core stays
a generic platform; Ticket/Rehearsal/Accounting become independently
extractable Domain Modules. This phase makes that concrete for the
first time - Contracts, a Capability-based Authorization boundary, and
the Rehearsal Module fully wired against both.

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
   Module          (boundary          (Auth split      Modules)
 (implemented,      defined,          this round,
  Contract-           not             Domain not
  adopted)         implemented)       yet split)
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
Notification Contract, Public Identity/Page, Join (Join Key, Membership/
Participation Request).

Core does not know what a Rehearsal, a Ticket, or an Accounting entry
is. It knows how to answer: who is this, does this Organization/
Production exist, is this Person a member, and can this Person do
`$capability` here.

---

# 4. Core Contracts

`plugin/src/Core/Contract/` (interfaces) + `plugin/src/Core/Adapter/`
(the concrete StageArt-hosted implementation of each, thin wrappers
around Core's existing Application/Domain services - no behavior
changed by introducing them).

| Contract | Method(s) | Adapter |
|---|---|---|
| `IdentityContract` | `resolveCurrentPersonId(int $wordPressUserId): ?PersonId` | `CoreIdentityAdapter` |
| `OrganizationContextContract` | `organizationExists(OrganizationId): bool` | `CoreOrganizationContextAdapter` |
| `ProductionContextContract` | `getProduction(ProductionId): ?ProductionSummary` | `CoreProductionContextAdapter` |
| `MembershipContract` | `activeProductionMemberPersonIds(ProductionId): PersonId[]` | `CoreMembershipAdapter` |
| `AuthorizationContract` | `resolveCurrentPersonId(int): ?PersonId`, `canForProduction(PersonId, ProductionId, string $capability): bool` | `CoreAuthorizationAdapter` |
| `NotificationContract` | `notify(PersonId, string $type, array $payload): void` | **None yet** - see §14 |

`ProductionSummary` (`plugin/src/Core/Contract/ProductionSummary.php`)
is a plain DTO (`id`, `name`, `status`) - deliberately narrower than the
full `Production` Domain Entity, so a Module depending on it isn't
depending on Core's internal Entity shape.

**Adopted so far**: the Rehearsal Module's `CreateRehearsalUseCase`/
`ConfirmRehearsalUseCase` depend on `MembershipContract` (§6).
`AuthorizationContract` is implemented and tested but not yet the
injected dependency at Rehearsal's 13 authorization call sites - those
still call `ProductionAuthorizationService::hasProductionCapability()`
directly (Core's own Application service, itself now Module-name-free -
see §5). This is a disclosed, deliberate scope limit, not every
dependency migrated.

---

# 5. Capability-based Authorization

**Before this phase**: `ProductionAuthorizationService` (Core) had
`canManageRehearsals()` and `canManageAccounting()` - two methods named
after two different Modules, living inside Core.

**After this phase**: both are **removed**. In their place:

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

Call sites changed from `$this->authorization->canManageRehearsals($p, $prod)`
to `$this->authorization->hasProductionCapability($p, $prod, RehearsalCapability::MANAGE)`
- **13 Rehearsal call sites, 9 Accounting call sites**, all mechanical,
all behavior-preserving (same underlying Role → Permission Set lookup;
`RehearsalCapability::MANAGE`/`AccountingCapability::MANAGE` are the
exact same string values `'Rehearsal.Update'`/`'Accounting.Update'` the
removed methods already used internally). Verified via the full
existing PHPUnit suite (542 tests) plus new tests targeting this
specifically (`tests/Core/Adapter/CoreAuthorizationAdapterTest.php`).

`AuthorizationContract::canForProduction(PersonId, ProductionId,
string $capability)` is the ID-based Contract version of the same
mechanism, for Modules that don't want to hold `Person`/`Production`
Domain Entities just to ask an authorization question.

A capability string with no matching `RolePermissions` entry (e.g.
`Accounting.Update` today - no `ACCOUNTING_MANAGER` `RoleKey` exists)
simply evaluates to false for a non-PrimaryManager caller. Core never
needs to know a capability exists ahead of time.

---

# 6. Rehearsal Module (the concrete implementation)

See `docs/modules/Rehearsal.md` for full detail. Summary:

- Domain/Application/Infrastructure already lived in their own
  namespaces (`Domain\Rehearsal`, `Domain\RehearsalAttendance`,
  `Domain\Timetable`, `Domain\TimetableItem`, their `Application\*`
  counterparts) before this phase - confirmed clean at the namespace/
  table level in the prior audit.
- This phase's change: `ProductionMemberResolver` (a Rehearsal-owned
  class that was really re-implementing a Core concern - "who is an
  active Production member") is **deleted**; its logic moved unchanged
  into `CoreMembershipAdapter`, and `CreateRehearsalUseCase`/
  `ConfirmRehearsalUseCase` now depend on `MembershipContract` instead.
- Authorization Module-name leak removed (§5).
- Two new tests specifically prove the boundary: a dependency-direction
  scan (`RehearsalModuleDependencyDirectionTest`) and a Fake-Contract
  isolation test (`RehearsalModuleContractIsolationTest`).

---

# 7. Ticket Module

Not implemented. `docs/modules/Ticket.md` is the Module Template (§16)
- responsibility, intended Core Contract usage, intended owned Domain/
Database, API/Authorization/Notification/WordPress-Adapter shape - so
future implementation starts from a defined boundary rather than an
empty page.

---

# 8. Accounting Module

Partially implemented (Budget/Expense/Account/JournalEntry, from before
this phase). This phase's change: the same Authorization Module-name
removal as Rehearsal (§5) - 9 call sites, `AccountingCapability::MANAGE`.
Domain/Application code itself (whether Accounting's Application layer
should adopt `ProductionContextContract`/`MembershipContract` the way
Rehearsal did) is **not** addressed this round - see
`docs/modules/Accounting.md`'s Open Items.

---

# 9. Database Ownership

No table was renamed. This section records which Module owns which
table's schema and business rules - Core does not query a Module's
tables directly, and a Module does not query another Module's tables
directly (all cross-Module data flows through Core Contracts).

| Table | Owner |
|---|---|
| `stageart_people`, `stageart_user_accounts`, `stageart_email_credentials`, `stageart_external_identities`, `stageart_email_verification_tokens`, `stageart_password_reset_tokens`, `stageart_refresh_tokens` | Core (Identity) |
| `stageart_organizations`, `stageart_organization_follows` | Core (Organization) |
| `stageart_productions`, `stageart_projects` | Core (Production Context) |
| `stageart_memberships`, `stageart_participants`, `stageart_production_delegates` | Core (Membership) |
| `stageart_join_keys` | Core (Join) |
| `stageart_favorites` | Core (Public Identity / Favorite) |
| `stageart_notification_read_states`, `stageart_push_preferences` | Core (Notification) |
| `stageart_rehearsals`, `stageart_rehearsal_attendances`, `stageart_timetables`, `stageart_timetable_items`, `stageart_timetable_item_participants`, `stageart_schedule_comments`, `stageart_timetable_version_published_notifications` | **Rehearsal Module** |
| `stageart_budgets`, `stageart_budget_lines`, `stageart_expenses`, `stageart_expense_lines`, `stageart_accounts`, `stageart_journal_entries`, `stageart_journal_entry_lines` | **Accounting Module** |
| *(none yet)* | Ticket Module |

Note: `stageart_timetable_version_published_notifications` is a
Rehearsal-owned table despite living conceptually next to Core's
Notification tables - see §14's disclosed coupling on
`TimetableVersionPublishedNotification`.

---

# 10. API Ownership

No route was renamed or moved. Existing routes are flat under
`stageart/v1/...`, not literally prefixed `/rehearsal/`/`/accounting/`
(`03-ModularArchitecture.md` §10 explicitly does not require breaking
existing routes retroactively) - Ownership here is a documentation
fact, not a URL-shape guarantee.

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

`mobile-rn/src/features/` is **not restructured** this round (explicit
instruction: don't break the existing Web/React Native structure for a
folder-naming exercise). Current ownership, documented rather than
moved:

| `features/` subfolder | Owner |
|---|---|
| `auth`, `person`, `organization`, `production`, `membership`, `participation`, `joinKey`, `favorite`, `notifications`, `pushPreference`, `dashboard`, `mypage` | Core |
| `attendance`, `schedule`, `printView` | **Rehearsal Module** |
| `accounting` | **Accounting Module** |
| *(none yet)* | Ticket Module |

Future direction (not executed this round): `features/{core,rehearsal,
ticket,accounting}/` subfolders, once a restructure is worth the churn
- the table above is the mapping such a restructure would follow.

---

# 12. WordPress Plugin Portability / Future Adapter

Unchanged policy from `03-ModularArchitecture.md` §7 - this phase makes
the *mechanism* real for the first time, not the destination:

```text
Rehearsal Module
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
```

No WordPress Plugin extraction was attempted this round (explicitly out
of scope, §18). What exists now: the Rehearsal Module's two migrated
dependencies (`MembershipContract`, and Authorization's Capability
mechanism) would work unchanged if a `WordPressAdapter` implementing
`MembershipContract`/`AuthorizationContract` were substituted for
`CoreMembershipAdapter`/`CoreAuthorizationAdapter` - no Rehearsal
Application code references the concrete Adapter classes, only the
interfaces.

---

# 13. API Policy

Confirmed unchanged from `03-ModularArchitecture.md` §10: no existing
route was renamed, moved, or had its behavior changed by this phase.
New routes for future Modules are not required to adopt a `/module-name/`
URL prefix retroactively-incompatible with existing conventions - `§10`'s
Ownership table is the source of truth, not the URL shape.

---

# 14. Known remaining coupling (disclosed, not fixed this round)

1. **`TimetableVersionPublishedNotification`** lives in Core's own
   `Domain\Notification` namespace, but its meaning
   ("a Timetable Version was published") is entirely Rehearsal/
   Timetable-specific. Not moved this round - it is a persisted Domain
   Entity with its own DB table, and renaming/relocating it is a
   migration-like risk this phase's scope did not justify. `docs/modules/Rehearsal.md`
   tracks this explicitly.
2. **`NotificationContract` has no adapter yet.** Core has no generic
   "create and dispatch a notification" mechanism to wrap - only the
   one concrete, Rehearsal-specific
   `TimetableVersionPublishedNotification` type exists. Building
   `CoreNotificationAdapter` was deferred rather than faked; the first
   Module with a real, generic notification need should build it then.
3. **12 of 22 Capability-check call sites (Rehearsal's remaining 13
   minus the 2 already Contract-based, plus all 9 of Accounting's)
   still depend on `ProductionAuthorizationService`/
   `ProductionRepositoryInterface` directly**, not on
   `AuthorizationContract`/`ProductionContextContract`. The generic,
   Module-name-free Capability mechanism (§5) is adopted everywhere;
   full ID-based Contract injection is not.
4. **Accounting Module's Application layer** has not been reviewed or
   migrated toward Core Contracts at all beyond the Authorization
   change - see `docs/modules/Accounting.md`.

---

# 15. Implementation Rule for Claude / Future Developers

Before adding a new Rehearsal/Ticket/Accounting feature, or starting a
new Module:

1. Does this belong in Core, or in a Module? (§3 - if it requires
   knowing what a Rehearsal/Ticket/Accounting entry *is*, it's a
   Module.)
2. Does a Core Contract already cover what this needs from Core? If
   not and it's a genuine, immediate need, add a narrow one (§4) rather
   than reaching into Core's concrete Domain/Infrastructure.
3. If this needs an Authorization check, does it belong to an existing
   Capability, or does it need a new one owned by the Module itself
   (§5)? Never add a Module-named method to
   `ProductionAuthorizationService`/`AuthorizationContract` again.
4. Which table(s) does this data belong to, and does that match the
   Module Ownership table (§9)? A Module should not read/write another
   Module's tables directly.
5. Update `docs/modules/<Module>.md` and this file's Ownership tables
   when the answer changes.

---

# 16. Module Template

Every `docs/modules/<Module>.md` should record, at minimum (this is
what `Rehearsal.md`/`Ticket.md`/`Accounting.md` now each do):

- Status (implemented / partially / not implemented)
- Responsibility (with a Blueprint doc citation)
- Core Contract usage (which Contracts, and whether actually adopted in
  code yet, or design-intent only)
- Entities/Domain the Module owns
- Database tables the Module owns
- API Ownership (which REST controllers)
- Authorization (Capability names it owns)
- Notification usage (if any)
- Future WordPress Adapter note
- Known coupling / open items, disclosed explicitly rather than implied
  complete

---

# 17. Final Policy

> StageArt Core provides Identity, Organization, Production Context,
> Membership, and a generic Capability-based Authorization mechanism -
> never a Module-named one. Rehearsal, Ticket, and Accounting are
> independent Domain Modules that consume Core exclusively through
> `StageArt\Core\Contract\*` interfaces, each owning its own Domain,
> Database tables, and Capability vocabulary. The Rehearsal Module is
> the first to fully demonstrate this pattern (Membership Contract
> adoption + Capability-based Authorization, both tested); Ticket and
> Accounting have a defined boundary to grow into, not yet full
> adoption.
