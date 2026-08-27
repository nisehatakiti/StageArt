# Accounting Management Module

Status: **Partially implemented** (Budget/Expense/Account/JournalEntry
predate the Core/Module Architecture work) - **fully Contract-adopted**
as of Phase 3 (the one disclosed exception Phase 2 left open is now
closed - see Authorization below). Domain/Application code itself was
not extended (real Accounting Domain work, e.g. `ACCOUNTING_MANAGER`
Role, stays explicitly out of scope) - only the dependency-boundary
migration. Package-boundary consolidation (its own `ModuleBootstrap`/
`Installer`/`ModuleDescriptor`, mirroring Rehearsal's Phase 3 work) is
evaluated but **not built** this phase - see "Package boundary
evaluation" below and `docs/architecture/WordPressPluginModuleBoundary.md`
§10.

## Responsibility

Budget/Expense/Settlement bookkeeping for a Production - see the
accounting-related docs under `docs/04-DomainModel/` (`Account.md`,
`JournalEntry.md`, and the various `*AccountingPolicy.md` /
`PayableRecognition`-related docs) for business rules.

## Entities owned by this Module

`Budget` / `BudgetLine`, `Expense` / `ExpenseLine`, `Account`,
`JournalEntry` / `JournalEntryLine` (`plugin/src/Domain/Budget/`,
`Domain/Expense/`, `Domain/Account/`, `Domain/JournalEntry/`). No single
unifying `Application/Accounting/` namespace exists yet - the Module
spans `Application/Budget/`, `Application/Expense/`,
`Application/JournalEntry/`, `Application/Account/`,
`Application/ProductionAccounting/`.
`Application/Accounting/AccountingCapability.php` is the first shared
piece of an eventual unified namespace.

## Core Contract usage (fully adopted)

All 13 UseCases that previously depended on
`ProductionRepositoryInterface`/`ProductionAuthorizationService`
directly were migrated to Core Contracts in Phase 2, mirroring the
Rehearsal Module's pattern:

| UseCase | Contracts depended on |
|---|---|
| `Budget\CreateBudgetUseCase`, `UpdateBudgetUseCase` | `ProductionContextContract` (incl. `getProductionOrganizationId()`), `IdentityContract`, `AuthorizationContract` |
| `Budget\GetBudgetUseCase`, `ActivateBudgetUseCase` | `IdentityContract`, `AuthorizationContract` (the Budget's own `productionId()` is enough - no Production lookup needed) |
| `Budget\ListBudgetsUseCase` | `ProductionContextContract`, `IdentityContract`, `AuthorizationContract` |
| `Expense\CreateExpenseUseCase`, `ListExpensesUseCase` | `ProductionContextContract`, `MembershipContract`, `IdentityContract` |
| `Expense\GetExpenseUseCase` | `IdentityContract`, `MembershipContract` |
| `Expense\UpdateExpenseUseCase`, `ConfirmExpenseUseCase` | `ProductionContextContract`, `IdentityContract`, `AuthorizationContract` |
| `JournalEntry\ListJournalEntriesUseCase` | `ProductionContextContract`, `IdentityContract`, `AuthorizationContract` |
| `ProductionAccounting\GetProductionAccountingSummaryUseCase` | `ProductionContextContract`, `MembershipContract`, `IdentityContract` |

The Organization a Budget/Expense/Account belongs to (needed to
validate an Account reference stays within the Production's own
Organization) is resolved via
`ProductionContextContract::getProductionOrganizationId()` - added this
phase specifically because Accounting is the one Module that genuinely
needs it (`ProductionSummary` itself deliberately stays minimal - see
`CoreModuleArchitecture.md` §4).

**Phase 2's one disclosed exception is closed as of Phase 3**:
`JournalEntry\PostJournalEntryUseCase`'s Organization-Scope branch (a
JournalEntry not tied to any Production) previously depended on
`Application\Organization\OrganizationAuthorizationService` directly
(it needed `hasRole($person, $organizationId, [RoleKey::OWNER])`, which
`AuthorizationContract` had no equivalent for). Phase 3 added
`AuthorizationContract::canForOrganization()` - a generic,
Capability-string-based Organization-Scope check, deliberately owned by
Core (`Core\Contract\OrganizationCapability::OWNER`), not Accounting -
and this branch now calls it instead. Accounting has **zero** remaining
direct Core Application-service dependencies.

Verified two ways:

- `tests/Core/ModuleDependencyDirectionTest.php::test_accounting_module_never_imports_core_internal_classes`
  scans `Application/Account`, `Budget`, `Expense`, `JournalEntry`,
  `ProductionAccounting` and their `Domain` counterparts for a direct
  import of `ProductionRepositoryInterface`,
  `ParticipantRepositoryInterface`, `PersonRepositoryInterface`, or the
  `Production`/`Participant`/`Person` Domain Entities - passes with zero
  violations.
- `tests/Core/ModuleBoundaryDependencyTest.php` (Phase 3) additionally
  confirms Accounting never imports Rehearsal's namespaces (and vice
  versa), and includes a positive check that Accounting genuinely uses
  a Core Contract, not just avoids Core internals.
- `tests/Application/Budget/BudgetModuleContractIsolationTest.php`
  runs `CreateBudgetUseCase` end to end (authorization, Organization
  resolution, Budget Line validation, Budget creation) against
  hand-written Fakes for `ProductionContextContract`,
  `IdentityContract`, `AuthorizationContract` - **zero** real Core
  Repository is touched (only `BudgetRepositoryInterface`/
  `AccountRepositoryInterface`, which are this Module's own).

## Owned data

`stageart_budgets`, `stageart_budget_lines`, `stageart_expenses`,
`stageart_expense_lines`, `stageart_accounts`, `stageart_journal_entries`,
`stageart_journal_entry_lines`. Unchanged by this phase - the migration
moved which class each UseCase calls, not which table it reads/writes.

## API boundary

`AccountRestController`, `BudgetRestController`, `ExpenseRestController`,
`JournalEntryRestController`, `ProductionAccountingRestController`.
No route was renamed, moved, or had its request/response shape changed
by this phase.

## Package boundary evaluation (Phase 3)

Evaluated whether Accounting can follow Rehearsal's
`ModuleBootstrap`/`Installer`/`ModuleDescriptor` pattern (§9-10 of
`docs/architecture/WordPressPluginModuleBoundary.md`): **yes,
structurally nothing blocks it** - all 13 UseCases are already
Contract-based, its 7 tables are self-contained (no other Module reads
them), and its 5 REST Controllers already form a clean unit. **Not
built this phase**, per the governing instruction's explicit scope
limit ("今回はAccountingを完全にRehearsalと同じレベルまで実証する必要は
ありません"). Concrete next steps if a future phase takes this on:
extract `AccountingInstaller` (mirroring `RehearsalInstaller`),
consolidate wiring into `AccountingModuleBootstrap`, add
`AccountingModuleDescriptor`, add an isolation test mirroring
`RehearsalModuleBootstrapIsolationTest`.

## Authorization

`ProductionAuthorizationService::canManageAccounting()` - a Core method
named after this Module - was removed in Phase 1. This phase completed
the migration for the Production-scoped path: every migrated UseCase
now calls `AuthorizationContract::canForProduction($personId,
$productionId, AccountingCapability::MANAGE)` (ID-based) instead of
`ProductionAuthorizationService::hasProductionCapability($person,
$production, ...)` (Entity-based), where `AccountingCapability::MANAGE
= 'Accounting.Update'` is owned by this Module's own
`plugin/src/Application/Accounting/AccountingCapability.php`, not by
Core.

**Behavior is unchanged**: no Role's Permission Set currently grants
`Accounting.Update` (no `ACCOUNTING_MANAGER` `RoleKey` entry exists in
`RolePermissions::MAP`), so this capability still evaluates to
PrimaryManager-only. Extending it to a real `ACCOUNTING_MANAGER` Role
(per `ProductionDelegatePolicy.md`'s "会計データ入力...はProductionDelegateに
許可する") is real Accounting Domain design work, still out of scope.

## Open items

- `AccountingModuleBootstrap`/`AccountingInstaller`/
  `AccountingModuleDescriptor` are designed (see "Package boundary
  evaluation" above) but not built - a future phase's responsibility if
  Accounting Plugin extraction becomes a real goal.
- `ACCOUNTING_MANAGER` Role/Permission Set definition remains
  unaddressed - a future Accounting-focused phase's responsibility.
- No single unifying `Application/Accounting/` namespace exists yet
  (the Module still spans five separate Application namespaces) - not
  restructured this phase, since it is a pure folder-organization
  change with no dependency-boundary implication.
