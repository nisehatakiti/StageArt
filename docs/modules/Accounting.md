# Accounting Management Module

Status: **Partially implemented** (Budget/Expense/Account/JournalEntry
predate the Core/Module Architecture work) - **Contract-adopted as of
Phase 2**, with one disclosed exception. Domain/Application code
itself was not extended this phase (real Accounting Domain work, e.g.
`ACCOUNTING_MANAGER` Role, stays explicitly out of scope) - only the
dependency-boundary migration.

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

## Core Contract usage (adopted this phase)

All 13 UseCases that previously depended on
`ProductionRepositoryInterface`/`ProductionAuthorizationService`
directly were migrated to Core Contracts, mirroring the Rehearsal
Module's pattern:

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

**One disclosed, intentional exception**:
`JournalEntry\PostJournalEntryUseCase`'s Organization-Scope branch (a
JournalEntry not tied to any Production) still depends on
`Application\Organization\OrganizationAuthorizationService` directly -
it needs `hasRole($person, $organizationId, [RoleKey::OWNER])`, a
Role-based check against a full `Person` Entity that
`AuthorizationContract` (Capability-string-based, Production-scoped
only) has no equivalent for. Its Production-Scope branch (the common
case) is fully Contract-based. See that file's own docblock.

Verified two ways:

- `tests/Core/ModuleDependencyDirectionTest.php::test_accounting_module_never_imports_core_internal_classes`
  scans `Application/Account`, `Budget`, `Expense`, `JournalEntry`,
  `ProductionAccounting` and their `Domain` counterparts for a direct
  import of `ProductionRepositoryInterface`,
  `ParticipantRepositoryInterface`, `PersonRepositoryInterface`, or the
  `Production`/`Participant`/`Person` Domain Entities - passes with zero
  violations (the one disclosed `OrganizationAuthorizationService`
  dependency above is not on this denylist; it is a different kind of
  Core Application service, not a Repository/Entity).
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

- `PostJournalEntryUseCase`'s Organization-Scope branch still depends
  on `OrganizationAuthorizationService` directly - see above and
  `CoreModuleArchitecture.md` §14.
- `ACCOUNTING_MANAGER` Role/Permission Set definition remains
  unaddressed - a future Accounting-focused phase's responsibility.
- No single unifying `Application/Accounting/` namespace exists yet
  (the Module still spans five separate Application namespaces) - not
  restructured this phase, since it is a pure folder-organization
  change with no dependency-boundary implication.
