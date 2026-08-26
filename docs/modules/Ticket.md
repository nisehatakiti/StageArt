# Ticket Management Module

Status: **Not implemented.** Confirmed by search - no `Domain/Ticket`,
`Domain/Reservation`, `Domain/CheckIn`, or any related Application/
Infrastructure/Presentation code exists in `plugin/src/`
(`docs/03-ModularArchitecture.md` §12 excludes "Ticket Management本格
実装" from this round's scope, so this is a documentation-only entry).

## Responsibility (per existing Blueprint, not yet built)

Ticket type/pricing, Reservation, Check-in, Sales - see
`docs/04-DomainModel/TicketPricingPolicy.md`,
`TicketConsistencyPolicy.md`, `TicketRevenueConsistencyPolicy.md`,
`ReservationCapacityPolicy.md`, `ReservationConsistencyPolicy.md`, and
`docs/04-DomainModel/Reservation.md` / `Ticket.md` / `CheckIn.md` /
`QRTicket.md` for the full business-rule Blueprint. None of this was
reviewed for Module-boundary fitness this round since there is no
implementation yet to review.

## Entities expected to be owned by this Module (design-time, not built)

`TicketType`, `Reservation`, `CheckIn`, per the Domain Model docs above.

## Connection point to Core (design intent, not built)

Per `03-ModularArchitecture.md` §5-6, this Module should reference
Production only via `ProductionId` and a Context-Provider-style
interface, following the same pattern already realized by the
Rehearsal Module (see `Rehearsal.md` in this folder) - that is the
concrete precedent to follow when Ticket work begins, not a new pattern
to invent.

## Owned data (design intent, not built)

None yet - no `stageart_tickets` / `stageart_reservations` /
`stageart_check_ins` tables exist in `Installer.php`.
