# StageArt Domain Modules

This folder records the Core/Module boundary for StageArt's three
independently-productizable business areas, per
`docs/03-ModularArchitecture.md` (the original policy Blueprint) and
`docs/architecture/CoreModuleArchitecture.md` (the concrete
implementation record - Core Contracts, Capability-based Authorization,
Database/API Ownership tables - read that document for the full
picture; this folder is per-Module detail).

This folder documents **boundaries and current status**, not business
rules - the actual business rules for each module remain in
`docs/04-DomainModel/` (e.g. `RehearsalManagementPolicy.md`,
`TicketPricingPolicy.md`, the various accounting policy docs). Nothing
here overrides those.

| Module | Implementation status | Core Contract adoption | Doc |
|---|---|---|---|
| Rehearsal Management | Implemented (Web β) | Membership Contract adopted + Capability-based Authorization; other dependencies not yet migrated | [Rehearsal.md](Rehearsal.md) |
| Accounting Management | Partially implemented (pre-Web-β phase; Budget/Expense/Account/JournalEntry exist) | Capability-based Authorization only this round | [Accounting.md](Accounting.md) |
| Ticket Management | Not implemented (Domain, Application, Infrastructure, and REST layers are all absent) | Module Template only - Contract usage is design intent | [Ticket.md](Ticket.md) |

No WordPress Plugin extraction, no concrete `WordPressAdapter`
implementation, and no `/apps` + `/core` + `/modules` top-level
directory restructuring exist yet -
`docs/architecture/CoreModuleArchitecture.md` §12 records what would
need to be substituted to add one, without attempting it.
