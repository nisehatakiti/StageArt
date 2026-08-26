# Ticket Management Module

Status: **Not implemented.** Confirmed by search - no `Domain/Ticket`,
`Domain/Reservation`, `Domain/CheckIn`, or any related Application/
Infrastructure/Presentation code exists in `plugin/src/`. This entry is
the **Module Template** the Core/Module Architecture phase requires
before real implementation begins - see
`docs/architecture/CoreModuleArchitecture.md` §16 for the general
template shape this follows, and `docs/modules/Rehearsal.md` for the
one Module that has actually adopted this structure so far.

## Responsibility (per existing Blueprint, not yet built)

Ticket type/pricing, Reservation, Check-in, Sales - see
`docs/04-DomainModel/TicketPricingPolicy.md`,
`TicketConsistencyPolicy.md`, `TicketRevenueConsistencyPolicy.md`,
`ReservationCapacityPolicy.md`, `ReservationConsistencyPolicy.md`, and
`docs/04-DomainModel/Reservation.md` / `Ticket.md` / `CheckIn.md` /
`QRTicket.md` for the full business-rule Blueprint. None of this was
reviewed for Module-boundary fitness this round since there is no
implementation yet to review, and none of it is implemented this round
either - `docs/03-ModularArchitecture.md` §12 and this phase's own
exclusion list both rule out "Ticket Management本格実装" here.

## Core Contract usage (design intent, not built)

When Ticket work begins, it should depend on `StageArt\Core\Contract\*`
(`plugin/src/Core/Contract/`), the same boundary the Rehearsal Module
now uses, not on Core's concrete Domain/Infrastructure classes:

- `IdentityContract` - resolve the current WordPress user to a `PersonId`.
- `ProductionContextContract::getProduction()` - read a Production's
  basic state (id/name/status) without depending on the `Production`
  Entity or `ProductionRepositoryInterface` directly.
- `MembershipContract` - if Ticket ever needs "is this Person a
  Production member" (e.g. member-only presale), reuse this rather than
  querying `ParticipantRepositoryInterface` itself.
- `AuthorizationContract::canForProduction(personId, productionId,
  'Ticket.Manage')` - a future `TicketCapability::MANAGE` constant
  (owned by the Ticket Module's own Application namespace, mirroring
  `RehearsalCapability`/`AccountingCapability`) requested against the
  generic Capability check. Core does not need a `canManageTicket()`
  method added for this - `AuthorizationContract` already has no
  Module-named methods to add one to.
- `NotificationContract` - defined but has no adapter yet (see
  `docs/architecture/CoreModuleArchitecture.md`'s "Known remaining
  coupling"); a Ticket confirmation/reminder notification would be the
  first real consumer, at which point building the adapter becomes
  worthwhile.
- `OrganizationContextContract` - if Ticket ever needs Organization-
  level context directly (not just via a Production), rather than
  `OrganizationRepositoryInterface`.

## Entities expected to be owned by this Module (design-time, not built)

`TicketType`, `Reservation`, `CheckIn`, per the Domain Model docs above.
Would live under `plugin/src/Domain/Ticket/` /
`plugin/src/Application/Ticket/` etc., mirroring Rehearsal's own
directory shape (`docs/modules/Rehearsal.md`).

## Owned data (design intent, not built)

None yet - no `stageart_tickets` / `stageart_reservations` /
`stageart_check_ins` tables exist in `Installer.php`. See
`docs/architecture/CoreModuleArchitecture.md` §9 for the Database
Ownership convention these future tables should follow (Module-prefixed
conceptually, Core never queries them directly).

## API boundary (design intent, not built)

Would register under `stageart/v1/...` following the existing flat
routing convention (see `docs/architecture/CoreModuleArchitecture.md`
§13 - existing routes are not retroactively re-prefixed, but nothing
requires a literal `/tickets/` URL prefix either; Ownership is tracked
in docs, not enforced by the URL shape).

## Authorization

PrimaryManager-exclusive by default for management actions (matching
every other Module's baseline), plus whatever future
`TICKET_MANAGER`-equivalent Delegate Role/Permission Set entries
`RolePermissions` gains when this is actually built - not invented here
ahead of real requirements.

## Future WordPress Adapter

Per `docs/architecture/CoreModuleArchitecture.md` §12: once built against
the Contracts above, Ticket Module code depends only on interfaces, not
on `StageArt\Core\Adapter\Core*Adapter` concrete classes - a future
`WordPressAdapter` implementing the same Contracts against a different
host's Identity/Production/Membership model could be substituted without
touching Ticket Module code itself.
