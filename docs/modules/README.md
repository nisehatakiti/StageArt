# StageArt Domain Modules

This folder records the Core/Module boundary for StageArt's three
independently-productizable business areas, per
`docs/03-ModularArchitecture.md` (the original policy Blueprint),
`docs/architecture/CoreModuleArchitecture.md` (the concrete
implementation record - Core Contracts, Capability-based Authorization,
Database/API Ownership tables), and `docs/architecture/
WordPressPluginModuleBoundary.md` (Phase 3 - Module Registration,
`*ModuleBootstrap`/`*Installer`, and the real proof that Rehearsal and
Accounting could each be registered from a separate WordPress Plugin) -
read those documents for the full picture; this folder is per-Module
detail.

This folder documents **boundaries and current status**, not business
rules - the actual business rules for each module remain in
`docs/04-DomainModel/` (e.g. `RehearsalManagementPolicy.md`,
`TicketPricingPolicy.md`, the various accounting policy docs). Nothing
here overrides those.

| Module | Implementation status | Core Contract adoption | Package boundary (Phase 3) | Doc |
|---|---|---|---|---|
| Rehearsal Management | Implemented (Web β) | Fully adopted - all 26 UseCases depend on Core Contracts only | `RehearsalModuleBootstrap`/`RehearsalInstaller`/`RehearsalModuleDescriptor` built, proven by a Bootstrap Isolation Test | [Rehearsal.md](Rehearsal.md) |
| Accounting Management | Partially implemented (pre-Web-β phase; Budget/Expense/Account/JournalEntry exist) | Fully adopted for all 15 UseCases - zero remaining disclosed exceptions | `AccountingModuleBootstrap`/`AccountingInstaller`/`AccountingModuleDescriptor` built, proven by a Bootstrap Isolation Test | [Accounting.md](Accounting.md) |
| Ticket Management | Not implemented (Domain, Application, Infrastructure, and REST layers are all absent) | Module Template only - Contract usage is design intent, updated to Phase 3's real Contract shapes | Template only - points at Rehearsal's/Accounting's real code as the pattern to copy | [Ticket.md](Ticket.md) |

No physical WordPress Plugin extraction, no concrete `WordPressAdapter`
implementation, and no `/apps` + `/core` + `/modules` top-level
directory restructuring exist yet. What does exist as of Phase 3: both
the Rehearsal and Accounting Modules' entire wiring is consolidated
behind their own `*ModuleBootstrap`, each proven swap-able by a
Bootstrap Isolation Test that runs the real, fully-wired object graph
against nothing but Fakes - see `docs/architecture/
WordPressPluginModuleBoundary.md` for what a physical split would still
require beyond this.
