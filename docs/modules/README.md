# StageArt Domain Modules

This folder records the Core/Module boundary for StageArt's three
independently-productizable business areas, per `docs/03-ModularArchitecture.md`
(the official Blueprint for this policy - read that document first).

This folder documents **boundaries and current status**, not business
rules - the actual business rules for each module remain in
`docs/04-DomainModel/` (e.g. `RehearsalManagementPolicy.md`,
`TicketPricingPolicy.md`, the various accounting policy docs). Nothing
here overrides those.

| Module | Implementation status | Doc |
|---|---|---|
| Rehearsal Management | Implemented (Web β) | [Rehearsal.md](Rehearsal.md) |
| Accounting Management | Partially implemented (pre-Web-β phase; Budget/Expense/Account/JournalEntry exist, not reviewed this round) | [Accounting.md](Accounting.md) |
| Ticket Management | Not implemented (Domain, Application, Infrastructure, and REST layers are all absent) | [Ticket.md](Ticket.md) |

None of the three has a WordPress Plugin extraction, an Adapter
implementation, or an `/apps` + `/core` + `/modules` directory
restructuring - `03-ModularArchitecture.md` §11 explicitly does not
require that restructuring now, and none of it was attempted this round
(see `docs/03-ModularArchitecture.md` §12 for the exclusion).
