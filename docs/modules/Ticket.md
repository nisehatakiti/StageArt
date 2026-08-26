# Ticket Management Module

Status: **Not implemented.** Confirmed by search - no `Domain/Ticket`,
`Domain/Reservation`, `Domain/CheckIn`, or any related Application/
Infrastructure/Presentation code exists in `plugin/src/`. This entry is
the **Module Template** the Core/Module Architecture requires before
real implementation begins - see
`docs/architecture/CoreModuleArchitecture.md` §16 for the general
template shape, and `docs/modules/Rehearsal.md` /
`docs/modules/Accounting.md` for the two Modules that have actually
adopted this structure in real code (Phase 2) - a real Ticket
implementation should follow their pattern directly, not just this
document's description of it.

## Responsibility (per existing Blueprint, not yet built)

Ticket type/pricing, Reservation, Check-in, Sales - see
`docs/04-DomainModel/TicketPricingPolicy.md`,
`TicketConsistencyPolicy.md`, `TicketRevenueConsistencyPolicy.md`,
`ReservationCapacityPolicy.md`, `ReservationConsistencyPolicy.md`, and
`docs/04-DomainModel/Reservation.md` / `Ticket.md` / `CheckIn.md` /
`QRTicket.md` for the full business-rule Blueprint. None of this is
implemented this phase either - "Ticket Management本格実装" stays
explicitly out of scope.

## Core Contract usage (design intent, not built)

When Ticket work begins, it should depend on `StageArt\Core\Contract\*`
(`plugin/src/Core/Contract/`) from the start - the same boundary the
Rehearsal Module and (with one disclosed exception) the Accounting
Module now actually use in code, not on Core's concrete Domain/
Infrastructure classes:

- `IdentityContract::resolveCurrentPersonId()` - resolve the current
  WordPress user to a `PersonId`.
- `ProductionContextContract::getProduction()` - read a Production's
  basic state (id/name/status) without depending on the `Production`
  Entity or `ProductionRepositoryInterface` directly.
  `getProductionOrganizationId()` is also available if Ticket ever
  needs to validate something (e.g. a pricing Account) stays within the
  Production's own Organization - the same need the Accounting Module
  has for Budget/Expense Account validation.
- `MembershipContract::isProductionMember()` /
  `activeProductionMemberPersonIds()` - if Ticket ever needs "is this
  Person a Production member" (e.g. member-only presale) or "who are
  this Production's current members" (e.g. a presale notification
  list), reuse these rather than querying
  `ParticipantRepositoryInterface` itself.
- `AuthorizationContract::canForProduction(personId, productionId,
  'Ticket.Manage')` - a future `TicketCapability::MANAGE` constant
  (owned by the Ticket Module's own Application namespace, mirroring
  `RehearsalCapability`/`AccountingCapability`) requested against the
  generic Capability check. Core does not need a `canManageTicket()`
  method added for this - `AuthorizationContract` already has no
  Module-named methods to add one to.
- `NotificationContract::notify()` - **has a real Adapter now**
  (`CoreNotificationAdapter` → `WordPressNotificationDispatcher`,
  firing a `do_action('stageart_notification', ...)` WordPress Action
  Hook - see `CoreModuleArchitecture.md` §14). A Ticket
  reservation-confirmation or reminder notification would be a second,
  independent caller of the same Contract the Rehearsal Module already
  uses for `timetable_version_published` - no new Adapter work needed,
  just a new `$type` string and (eventually) a listener on the hook.
- `OrganizationContextContract::organizationExists()` - if Ticket ever
  needs Organization-level context directly (not just via a
  Production), rather than `OrganizationRepositoryInterface`. Still
  unused by any Module as of Phase 2 - Ticket could be its first real
  caller.

## Testing pattern to follow (design intent, not built)

Once built, a real Ticket UseCase should have:

- A namespace/import-scanning Architecture Test entry (extend
  `tests/Core/ModuleDependencyDirectionTest.php` with a
  `TICKET_MODULE_DIRECTORIES` list and a third `test_ticket_module_...`
  method, following the same denylist already used for Rehearsal/
  Accounting) proving it never imports
  `ProductionRepositoryInterface`/`ParticipantRepositoryInterface`/the
  `Production`/`Participant`/`Person` Domain Entities directly.
- A Fake-Contract isolation test (mirroring
  `RehearsalModuleContractIsolationTest`/`BudgetModuleContractIsolationTest`
  in `tests/Support/Fake*Contract.php`) proving at least one core
  UseCase (e.g. reservation creation) runs correctly with **zero** real
  Core Repository involved.

## Entities expected to be owned by this Module (design-time, not built)

`TicketType`, `Reservation`, `CheckIn`, per the Domain Model docs above.
Would live under `plugin/src/Domain/Ticket/` /
`plugin/src/Application/Ticket/` etc., mirroring Rehearsal's/
Accounting's own directory shape.

## Owned data (design intent, not built)

None yet - no `stageart_tickets` / `stageart_reservations` /
`stageart_check_ins` tables exist in `Installer.php`. See
`docs/architecture/CoreModuleArchitecture.md` §9 for the Database
Ownership convention these future tables should follow (Module-owned,
Core never queries them directly, no other Module queries them
directly either).

## API boundary (design intent, not built)

Would register under `stageart/v1/...` following the existing flat
routing convention (see `docs/architecture/CoreModuleArchitecture.md`
§10/§13 - existing routes are not retroactively re-prefixed, and
nothing requires a literal `/tickets/` URL prefix either; Ownership is
tracked in docs, not enforced by the URL shape). The Controller itself
should stay thin and call a Ticket Module Application Service/UseCase -
no business logic embedded in the Controller, matching every existing
Module's Controller shape.

## Authorization

PrimaryManager-exclusive by default for management actions (matching
every other Module's baseline), plus whatever future
`TICKET_MANAGER`-equivalent Delegate Role/Permission Set entries
`RolePermissions` gains when this is actually built - not invented here
ahead of real requirements.

## Future WordPress Adapter

Per `docs/architecture/CoreModuleArchitecture.md` §12: once built against
the Contracts above, Ticket Module code should depend only on
interfaces, never on `StageArt\Core\Adapter\Core*Adapter` concrete
classes - a future `WordPressAdapter` implementing the same Contracts
against a different host's Identity/Production/Membership model could
then be substituted without touching Ticket Module code. This is no
longer a purely theoretical claim for this codebase - the Rehearsal and
Accounting Modules' own Fake-Contract isolation tests demonstrate the
same swap-ability in practice, and a real Ticket implementation should
add an equivalent test rather than assume the boundary holds.
