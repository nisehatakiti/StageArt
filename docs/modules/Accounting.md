# Accounting Management Module

Status: **Partially implemented** (Budget/Expense/Account/JournalEntry
predate the Core/Module Architecture work) - **fully Contract-adopted**
and **package-boundary-consolidated** as of Phase 3, matching Rehearsal:
its own `AccountingModuleBootstrap`/`AccountingInstaller`/
`AccountingModuleDescriptor` (`plugin/src/Accounting/`) exist, proven by
`AccountingModuleBootstrapIsolationTest`. Domain/Application code itself
was not extended (real Accounting Domain work, e.g. `ACCOUNTING_MANAGER`
Role, stays explicitly out of scope) - only the dependency-boundary and
package-boundary migration. See `docs/architecture/
WordPressPluginModuleBoundary.md` §10.

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

All 15 UseCases that previously depended on Core internals directly
(`ProductionRepositoryInterface`/`ProductionAuthorizationService`/
`OrganizationRepositoryInterface`/`OrganizationAuthorizationService`)
are migrated to Core Contracts, mirroring the Rehearsal Module's
pattern:

| UseCase | Contracts depended on |
|---|---|
| `Budget\CreateBudgetUseCase`, `UpdateBudgetUseCase` | `ProductionContextContract` (incl. `getProductionOrganizationId()`), `IdentityContract`, `AuthorizationContract` |
| `Budget\GetBudgetUseCase`, `ActivateBudgetUseCase` | `IdentityContract`, `AuthorizationContract` (the Budget's own `productionId()` is enough - no Production lookup needed) |
| `Budget\ListBudgetsUseCase` | `ProductionContextContract`, `IdentityContract`, `AuthorizationContract` |
| `Expense\CreateExpenseUseCase` | `ProductionContextContract`, `MembershipContract`, `IdentityContract` |
| `Expense\ListExpensesUseCase` | `ProductionContextContract`, `MembershipContract`, `IdentityContract`, `AuthorizationContract` (every Expense of the Production with `Expense.Approve`; otherwise a Production member sees only the Expenses they created) |
| `Expense\GetExpenseUseCase` | `IdentityContract`, `MembershipContract`, `AuthorizationContract` (an approver with `Expense.Approve`, or the Expense's own creator while a member) |
| `Expense\UpdateExpenseUseCase` | `ProductionContextContract`, `IdentityContract` (the applicant only - the requester's Person ID must be the Expense's creator's; no Capability is consulted, ExpenseApprovalWorkflow.md Version 1.11) |
| `Expense\ConfirmExpenseUseCase` (retired: refuses every request, REST 410) | `IdentityContract`, `AuthorizationContract` |
| `Expense\ApplicantExpenseTransitionUseCase` (submit / withdraw / resubmit) | `IdentityContract`, `MembershipContract`, plus `ExpenseWorkflowNotifier` |
| `Expense\ApproveExpenseUseCase` | `ProductionContextContract`, `OrganizationContextContract` (`isAccountingEnabled()`), `IdentityContract`, `AuthorizationContract` (`Expense.Approve`), plus `StandardPayableAccountResolver` and `ExpenseWorkflowNotifier` |
| `Expense\RejectExpenseUseCase` | `IdentityContract`, `AuthorizationContract` (`Expense.Approve`), plus `ExpenseWorkflowNotifier` |
| `Expense\ExpenseWorkflowNotifier` (not a UseCase: the notifications of the three above) | `NotificationContract`, `ProductionCapabilityDirectoryContract`, `ProductionContextContract` |
| `JournalEntry\ListJournalEntriesUseCase` | `ProductionContextContract`, `IdentityContract`, `AuthorizationContract` |
| `ProductionAccounting\GetProductionAccountingSummaryUseCase` | `ProductionContextContract`, `MembershipContract`, `IdentityContract` |
| `Account\CreateAccountUseCase` | `OrganizationContextContract`, `IdentityContract`, `AuthorizationContract` (`OrganizationCapability::OWNER`) |
| `Account\ListAccountsUseCase` | `OrganizationContextContract`, `IdentityContract`, `AuthorizationContract` (`OrganizationCapability::MEMBER` - any ACTIVE Membership, broader than Create's Owner-only) |

`CreateAccountUseCase`/`ListAccountsUseCase` were found still depending
on `OrganizationAuthorizationService`/`OrganizationRepositoryInterface`
directly while building `AccountingModuleBootstrap` (Phase 2's own
13-UseCase count missed them, mirroring the PrintView/ProductionSchedule
gap Phase 3 found for Rehearsal) - migrated the same session. This gave
`OrganizationContextContract` its first real caller (defined in Phase 1,
unused until now) and required adding `OrganizationCapability::MEMBER`
alongside the existing `OWNER`.

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
- `tests/Accounting/AccountingModuleBootstrapIsolationTest.php` (Phase
  3) proves the entire `AccountingModuleBootstrap`-wired object graph
  (REST Controller -> UseCase -> Repository) works using only Fakes,
  including a new, minimal inline `FakeOrganizationContextContract` -
  the first Fake this Contract needed.

## Owned data

`stageart_accounts`, `stageart_budgets`, `stageart_budget_lines`,
`stageart_journal_entries`, `stageart_journal_entry_lines`,
`stageart_expenses`, `stageart_expense_lines` (and, since the Expense
approval workflow, `stageart_expense_reviews` and
`stageart_legacy_expense_migration_audit` - see "Expense approval
workflow") - owned and migrated by
`AccountingInstaller` (`plugin/src/Accounting/`), extracted verbatim
(byte-identical SQL) from Core's previously-monolithic installer, called
from Core's `Installer::install()` exactly as `RehearsalInstaller` is.
Verified live against the ConoHa dev DB: `Installer::install()` invoked
directly, all 7 tables confirmed present afterward.

## API boundary

`AccountRestController`, `BudgetRestController`, `ExpenseRestController`,
`JournalEntryRestController`, `ProductionAccountingRestController` -
all 5 constructed inside `AccountingModuleBootstrap`. No route was
renamed, moved, or had its request/response shape changed - verified
live: `rest_api_init` triggered against the real environment, all 10
Accounting routes present and byte-identical to before this refactor.

## Package boundary (Phase 3, built)

`plugin/src/Accounting/` mirrors `plugin/src/Rehearsal/` exactly:

- `AccountingModuleBootstrap` - constructs all 15 UseCases and all 5
  REST Controllers from Core Contracts (`ProductionContextContract`,
  `OrganizationContextContract`, `IdentityContract`,
  `AuthorizationContract`, `MembershipContract`) + this Module's own 4
  Repository interfaces + `TransactionManagerInterface`.
  `Presentation\Plugin::boot()` is its only caller today.
- `AccountingModuleDescriptor` - `moduleId() = 'accounting'`, its own
  `version()`, `requiredContracts()` (`ProductionContextContract`,
  `OrganizationContextContract`, `IdentityContract`,
  `AuthorizationContract`, `MembershipContract` - `NotificationContract`
  was deliberately omitted then, since no Accounting UseCase called it;
  the Expense approval workflow added it and
  `ProductionCapabilityDirectoryContract`, see below),
  `ownedTables()`. Registers into `Core\Module\ModuleRegistry` alongside
  `RehearsalModuleDescriptor`. Phase 4 §2's audit found
  `requiredContracts()` had drifted out of sync with
  `AccountingModuleBootstrap`'s actual constructor (missing
  `OrganizationContextContract`, added when `CreateAccountUseCase`/
  `ListAccountsUseCase` were migrated) - corrected by re-deriving the
  list from the Bootstrap's real dependencies rather than assumption.
- `AccountingInstaller` - owns this Module's tables' migration (7 tables
  at Phase 3; 9 since the Expense approval workflow, see below).

## Expense approval workflow (2026-10-30, `integrate/expense-approval-20261029`)

Business rules: `docs/04-DomainModel/ExpenseApprovalWorkflow.md`
(Version 1.11). What it added to this Module's boundary:

- **UseCases:** `Expense\ApplicantExpenseTransitionUseCase` (submit /
  withdraw / resubmit), `Expense\ApproveExpenseUseCase` (approval posts
  one Journal Entry) and `Expense\RejectExpenseUseCase` (差し戻し), all
  constructed in `AccountingModuleBootstrap` and called by
  `ExpenseRestController` (`PATCH /expenses/{id}/submit`, `withdraw`,
  `resubmit`, `approve`, `reject`). `ConfirmExpenseUseCase` stays wired
  but refuses every request (410). As of 2026-10-30 the Bootstrap
  constructs 21 UseCases in total.
- **Contracts:** `AccountingModuleBootstrap` and
  `AccountingModuleDescriptor::requiredContracts()` now also take
  `NotificationContract` (the workflow notifications, sent after the
  commit) and `ProductionCapabilityDirectoryContract` (who may approve a
  Production's Expenses, for the submission notification; Core's
  `CoreProductionCapabilityDirectoryAdapter` provides it).
- **Capability:** `AccountingCapability::APPROVE_EXPENSE`
  (`Expense.Approve`: the PrimaryManager, a proxy (代理人) or an ACTIVE
  ACCOUNTING_MANAGER Delegate) for approving, rejecting and seeing every
  Expense of a Production.
- **Tables** (`ownedTables()`): `stageart_expense_reviews` (schema
  1.44.0: the append-only 申請・承認履歴, with the approval workflow
  columns added to `stageart_expenses`) and
  `stageart_legacy_expense_migration_audit` (schema 1.45.0: the
  operations audit of the legacy Expense migration and its revert).
- **Operator tools, not UseCases:** `Accounting\LegacyExpenseMigration`,
  `LegacyExpenseMigrationRestore`, `LegacyExpenseMigrationAudit`,
  `LegacyExpenseMigrationClassifier`, `LegacyExpenseMigrationOperator`
  and `LegacyExpenseMigrationDatabaseError`, run only through WP-CLI
  (`plugin/scripts/legacy_expense_migration*.php`), never by a schema
  upgrade or a web request. See
  `docs/handoffs/LegacyExpenseMigration-Runbook.md`.

## Core -> Accounting reverse dependency (Phase 4 §2 investigation)

Explicitly re-investigated per Phase 4 §2: does any Core-owned code
(`src/Core/`, `Application/Dashboard`, `Application/Notification`, or
any other Core Application namespace) import Accounting's
Domain/Application classes directly, the way `GetMyDashboardUseCase`
used to import Rehearsal's Repository interfaces before Phase 4 §1?
**No such dependency was found** - a full `use`-statement scan across
every Core-owned directory for `StageArt\Domain\{Budget,Expense,
Account,JournalEntry}\*`/`StageArt\Application\{Budget,Expense,Account,
JournalEntry,ProductionAccounting}\*`/`StageArt\Accounting\*` imports
returned zero matches outside Accounting's own files and
`Infrastructure\WordPress\Schema\Installer::install()`'s single,
expected, disclosed call to `AccountingInstaller::install()`. No
Provider-Contract inversion (the pattern Phase 4 §1 built for
Rehearsal's Dashboard case) is needed for Accounting - there is no real
caller to invert against, and none was guessed at speculatively.

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

- `ACCOUNTING_MANAGER` Role/Permission Set definition remains
  unaddressed - a future Accounting-focused phase's responsibility.
- No per-Module schema version exists yet - `AccountingInstaller` is
  still gated by Core's one shared `SchemaUpgrader::CURRENT_VERSION`,
  same as `RehearsalInstaller`.
- No single unifying `Application/Accounting/` namespace exists yet
  (the Module still spans five separate Application namespaces) - not
  restructured this phase, since it is a pure folder-organization
  change with no dependency-boundary implication.
