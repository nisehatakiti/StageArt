# Accounting Management Module

Status: **Partially implemented**, from a phase before this Web β work
(not touched or reviewed in depth this round - see
`docs/03-ModularArchitecture.md` §12, which excludes "Accounting
Management本格実装" from this round's scope).

## Responsibility

Budget/Expense/Settlement bookkeeping for a Production - see the
accounting-related docs under `docs/04-DomainModel/` (`Account.md`,
`JournalEntry.md`, and the various `*AccountingPolicy.md` /
`PayableRecognition`-related docs merged in from `origin/main` this
round) for business rules.

## Entities owned by this Module (confirmed present)

`Budget` / `BudgetLine`, `Expense` / `ExpenseLine`, `Account`,
`JournalEntry` / `JournalEntryLine` (`plugin/src/Domain/Budget/`,
`Domain/Expense/`, `Domain/Account/`, `Domain/JournalEntry/`).

## Connection point to Core

Spot-checked `Budget.php`: references Core only via `ProductionId` /
`PersonId`, matching the same identifier-only pattern confirmed for
Rehearsal (see `Rehearsal.md` in this folder). A full coupling review
(equivalent to what was done for Rehearsal this round) was **not**
performed for Accounting - flagged as an open item for whenever
Accounting work resumes, not claimed as verified here.

## Owned data

`stageart_budgets`, `stageart_budget_lines`, `stageart_expenses`,
`stageart_expense_lines`, `stageart_accounts`, `stageart_journal_entries`,
`stageart_journal_entry_lines`.

## Coupling finding

`ProductionAuthorizationService` (Core) exposes `canManageAccounting()`
directly - the same naming-coupling pattern noted in `Rehearsal.md`.
Same recommendation: call the underlying generic
`hasProductionPermission()` directly from Accounting Application code
instead, when this area is revisited.

## Open items

- No coupling/data-ownership review beyond the one spot-check above.
- Not evaluated against Web β's newer Membership/Participant states
  (REQUESTED/PENDING) or against the Modular Architecture blueprint's
  full checklist (`03-ModularArchitecture.md` §12).
