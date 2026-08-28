# Performance & Ticket Module

Status: **Blueprint updated / implementation not started.**

This Module is not a standalone "ticket sales feature". It is the module that owns the operational chain from a StageArt Production to its individual performance sessions and the tickets sold or reserved for those sessions.

## Confirmed Canonical Structure

The canonical relationship is:

```text
Organization
↓
Project
↓
Production
↓
PerformanceSession（公演回）
↓
Ticket / TicketType / TicketPrice
↓
Reservation
↓
IssuedTicket
↓
CheckIn
```

`Production` remains a **StageArt Core** concept.

`PerformanceSession` and all ticketing concepts below it belong to this Module.

The Module must therefore be designed as **Performance & Ticket Module**, not as an isolated Ticket-only module.

## Responsibility

This Module owns:

- PerformanceSession（公演回）
- Ticket / TicketType
- TicketPrice
- Reservation
- IssuedTicket
- CheckIn
- QRTicket
- Ticket sales and capacity rules
- Session-specific availability and ticket settings

The module is responsible for turning one Production into one or more concrete public performance sessions.

Example:

```text
Production
「12人のうかれる人々」

├─ PerformanceSession
│  2026/10/10 14:00
│
├─ PerformanceSession
│  2026/10/10 19:00
│
├─ PerformanceSession
│  2026/10/11 14:00
│
└─ PerformanceSession
   2026/10/11 19:00
```

Each PerformanceSession may have its own TicketTypes, prices, sales availability and capacity.

## Core / Module Boundary

### StageArt Core owns

- Organization
- Project
- Production
- Person / Identity
- Membership
- Production authorization
- Production context

### Performance & Ticket Module owns

```text
Production reference
        ↓
PerformanceSession
        ↓
TicketType / Ticket / TicketPrice
        ↓
Reservation
        ↓
IssuedTicket / CheckIn / QRTicket
```

The Module must never redefine or duplicate `Production`.

A PerformanceSession always belongs to exactly one Production through the Production ID provided by Core Contracts.

## Why PerformanceSession belongs with Ticket

The relationship between a Production and its concrete public performances is part of the same reusable business capability as ticket management.

Without PerformanceSession, Ticket would have to invent its own event/date structure.

Keeping them together makes the module reusable outside StageArt as a future WordPress plugin:

```text
Event / Production
↓
Performance Sessions
↓
Tickets
↓
Reservations
↓
Check-in
```

Therefore the WordPress product boundary should be a single **Performance & Ticket Plugin**, while StageArt Core provides the Production context when the module runs inside StageArt.

## WordPress Plugin Reuse Goal

The future plugin must support two hosting modes through adapters.

### StageArt mode

```text
StageArt Core
    ↓ Contract
Performance & Ticket Module
```

The module receives Production, Identity, Membership and Authorization information through Core Contracts.

### Standalone WordPress mode

```text
WordPress host / Event adapter
        ↓ Contract
Performance & Ticket Module
```

The same Module code should be reusable when another WordPress plugin or host application provides an Event/Production-like context.

The module itself must not depend on StageArt Core concrete entities or WordPress-specific business models.

## Core Contract Usage

When implementation begins, the module should depend only on `StageArt\Core\Contract\*` or equivalent host adapters, never directly on Core Domain or Infrastructure classes.

Expected StageArt contracts include:

- `IdentityContract`
- `ProductionContextContract`
- `MembershipContract`
- `AuthorizationContract`
- `NotificationContract`

The existing generic capability mechanism must be used. Core must not gain a new module-named method such as `canManageTickets()`.

Example capability:

```text
TicketCapability::MANAGE
→ Ticket.Manage
```

Production authorization is evaluated through:

```text
AuthorizationContract::canForProduction(
    personId,
    productionId,
    TicketCapability::MANAGE
)
```

## PerformanceSession Domain Rules

A PerformanceSession represents one concrete occurrence of a Production.

Expected information includes:

- Production ID
- Session date
- Opening time
- Start time
- End time when applicable
- Venue reference when applicable
- Session status
- Capacity when applicable
- Public visibility / sales availability

A Production may have zero or more PerformanceSessions.

A Ticket must be associated with a PerformanceSession rather than directly representing an unspecified Production-wide date, unless a future explicit multi-session ticket rule is introduced.

## Ticket Domain Rules

The detailed Ticket Blueprint remains the source of truth for pricing, reservation consistency, capacity and revenue rules.

Relevant existing documents include:

- `TicketPricingPolicy.md`
- `TicketConsistencyPolicy.md`
- `TicketRevenueConsistencyPolicy.md`
- `ReservationCapacityPolicy.md`
- `ReservationConsistencyPolicy.md`
- `Reservation.md`
- `Ticket.md`
- `CheckIn.md`
- `QRTicket.md`

Future implementation must align those rules to the newly confirmed hierarchy:

```text
Production
↓
PerformanceSession
↓
Ticket
↓
Reservation / IssuedTicket / CheckIn
```

## Module Package Boundary

Follow the real Rehearsal Module pattern established in Phase 3.

Expected package boundary:

```text
plugin/src/PerformanceTicket/
├─ PerformanceTicketModuleDescriptor
├─ PerformanceTicketModuleBootstrap
└─ PerformanceTicketInstaller
```

Existing DDD Domain/Application/Infrastructure/Presentation directories may remain layered as necessary, but the Module package must explicitly own:

- Module descriptor
- Module bootstrap
- Module installer
- PerformanceSession capability definitions
- Ticket capability definitions
- Module REST registration
- Module-owned database tables

## Expected Module Descriptor

The descriptor should declare:

- moduleId: `performance-ticket`
- module version
- required Core/host contracts
- owned database tables
- required Core version when running inside StageArt

## Database Ownership

The Module must own its own tables.

Expected future ownership includes tables for:

- performance sessions
- ticket definitions / types / prices as required by the confirmed model
- reservations
- issued tickets when persistence requires separation
- check-ins

Exact table names are implementation design work and must be decided before implementation.

Core must not query these tables directly, and Rehearsal / Accounting must not query them directly.

Cross-module interaction must occur through contracts or application-level APIs.

## REST API Boundary

REST Controllers remain thin.

Expected flow:

```text
REST Controller
↓
Module UseCase / Application Service
↓
Module Repository
```

No business rules belong in REST Controllers.

Existing routes are not to be retroactively broken merely to enforce a module URL prefix.

## Testing Requirements

Before implementation is considered complete, add:

1. Module dependency-direction tests proving no direct import of Core repositories or Core Domain entities.
2. Module boundary tests proving no Performance & Ticket ↔ Rehearsal/Accounting concrete namespace dependencies.
3. Fake Contract isolation tests proving a real PerformanceSession/Ticket UseCase can run without Core repositories.
4. Bootstrap isolation tests proving the wired Controller → UseCase → Repository graph works with Fakes.
5. Database ownership tests.
6. API compatibility tests.
7. Production → PerformanceSession → Ticket lifecycle tests.
8. Reservation capacity and consistency tests.
9. Check-in and QR lifecycle tests when those features are implemented.

## Implementation Status

No Ticket implementation exists yet.

No PerformanceSession implementation exists yet.

The next implementation phase must begin with a design audit of the existing Ticket-related Blueprint documents so their detailed business rules are mapped consistently to the confirmed canonical hierarchy before creating Domain code.

## Confirmed Architectural Decision

The StageArt Ticket capability is confirmed as a **Production-context Module** with the canonical hierarchy:

```text
Production
↓
PerformanceSession（公演回）
↓
Ticket
```

The future commercial WordPress product is based on the same hierarchy and should be packaged as a reusable **Performance & Ticket Plugin**, rather than splitting PerformanceSession away from ticket management.
