# Accounting Management Module

Status: **Partially implemented** (Budget/Expense/Account/JournalEntry
predate this Web β work) - **Authorization now formally separated**
this round (Core/Module Architecture phase); Domain/Application code
itself not otherwise touched (`docs/03-ModularArchitecture.md` §12
excludes "Accounting Management本格実装" - real Domain design work -
from this round's scope, but the Module-boundary/Authorization cleanup
itself is explicitly this round's subject).

## Responsibility

Budget/Expense/Settlement bookkeeping for a Production - see the
accounting-related docs under `docs/04-DomainModel/` (`Account.md`,
`JournalEntry.md`, and the various `*AccountingPolicy.md` /
`PayableRecognition`-related docs) for business rules.

## Entities owned by this Module (confirmed present)

`Budget` / `BudgetLine`, `Expense` / `ExpenseLine`, `Account`,
`JournalEntry` / `JournalEntryLine` (`plugin/src/Domain/Budget/`,
`Domain/Expense/`, `Domain/Account/`, `Domain/JournalEntry/`). No single
unifying `Application/Accounting/` namespace exists yet - the Module
spans `Application/Budget/`, `Application/Expense/`,
`Application/JournalEntry/`, `Application/Account/`,
`Application/ProductionAccounting/`. `Application/Accounting/AccountingCapability.php`
(new this round) is the first shared piece of an eventual unified
namespace.

## Connection point to Core

Spot-checked `Budget.php`: references Core only via `ProductionId` /
`PersonId`, matching the same identifier-only pattern confirmed for
Rehearsal (see `Rehearsal.md`). A full Core Contract adoption
(`StageArt\Core\Contract\*` - see `Ticket.md`'s "Core Contract usage"
section for what that would look like) was **not** performed for
Accounting this round - Accounting's 9 UseCases still depend on
`ProductionAuthorizationService`/`ProductionRepositoryInterface`
directly, same as before, just with the Capability-based authorization
call (see below). Flagged as an open item for a future Accounting-
focused phase, not claimed as done here.

## Owned data

`stageart_budgets`, `stageart_budget_lines`, `stageart_expenses`,
`stageart_expense_lines`, `stageart_accounts`, `stageart_journal_entries`,
`stageart_journal_entry_lines`.

## Authorization (changed this round)

`ProductionAuthorizationService::canManageAccounting()` - a Core method
named after this Module - has been **removed**. All 9 call sites
(`Application/Budget/*UseCase.php` ×5, `Application/Expense/*UseCase.php`
×2, `Application/JournalEntry/*UseCase.php` ×2) now call the generic
`hasProductionCapability($person, $production, AccountingCapability::MANAGE)`
instead, where `AccountingCapability::MANAGE = 'Accounting.Update'` is
owned by this Module's own Application namespace
(`plugin/src/Application/Accounting/AccountingCapability.php`), not by
Core.

**Behavior is unchanged**: no Role's Permission Set currently grants
`Accounting.Update` (no `ACCOUNTING_MANAGER` `RoleKey` entry exists in
`RolePermissions::MAP`), so this capability still evaluates to
PrimaryManager-only, exactly as `canManageAccounting()` did before this
refactor. Extending it to a real `ACCOUNTING_MANAGER` Role (per
`ProductionDelegatePolicy.md`'s "会計データ入力...はProductionDelegateに
許可する") is real Accounting Domain design work, still out of scope.

## Open items

- No Core Contract adoption (`ProductionContextContract`/
  `MembershipContract`) for Accounting's Application layer yet - only
  the Authorization piece was formally separated this round.
- No coupling/data-ownership review beyond the one spot-check above.
- Not evaluated against Web β's newer Membership/Participant states
  (REQUESTED/PENDING) or against the Modular Architecture blueprint's
  full checklist (`03-ModularArchitecture.md` §12).
- `ACCOUNTING_MANAGER` Role/Permission Set definition remains
  unaddressed - a future Accounting-focused phase's responsibility.
