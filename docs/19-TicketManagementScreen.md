# StageArt Blueprint
# Chapter 19 : Ticket Management Screen Specification

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the confirmed screen specification for managing ticket types and prices for a Production.

Ticket management is configured at the **Production level** and is common across all Performances belonging to that Production.

The business hierarchy is:

Organization
↓
Production
├── Member Management
├── Rehearsal Management
├── Performance Management
└── Ticket Management
      ↓
    Reception / Check-in

Performance Management and Ticket Management are both Production-level management operations. Reception / check-in is performed for an individual Performance.

---

## 2. Ticket Management Screen

The screen is entered from the Production information/management context through **チケット管理**.

The purpose of the screen is to register and edit the ticket types and their prices used for the Production.

Ticket type and price configuration is **common across the Production's Performances**.

The ticket management screen does not require separate ticket-price configuration for each Performance.

---

## 3. Ticket Type Registration

Each ticket entry consists of exactly two fields:

- **チケット種別**
- **金額**

Conceptually:

```text
チケット種別              金額
[一般                  ]    [2,000円]
```

No additional ticket-definition fields are required by this specification.

In particular, the ticket type does not have a separate **備考** field.

---

## 4. Multiple Ticket Types

The screen must allow multiple ticket types to be registered for the same Production.

The initial screen provides one ticket type/price row.

A **＋** button is provided to add another row.

Conceptually:

```text
チケット種別              金額
[一般                  ]    [2,000円]

チケット種別              金額
[学生                  ]    [1,000円]

チケット種別              金額
[（M/D日分）一般       ]    [1,500円]

        ＋ チケット種別を追加
```

An existing row may be removed as an implementation/UX detail.

The confirmed requirement is that the user can add multiple ticket type/price rows using the **＋** operation.

---

## 5. Performance-specific Discounts

Ticket types and prices are common across the Production rather than separately configured for each Performance.

When a discount or other special price applies to a particular Performance or date, the user expresses that distinction in the **チケット種別** text itself.

For example:

```text
（M/D日分）一般
```

may be registered as a ticket type.

The system therefore does not require a separate Performance-specific discount field, date-condition field, or ticket-price override field as part of this screen specification.

This is intentional: the ticket definition remains simple while allowing the user to describe special pricing through the ticket type name.

---

## 6. Save

The screen provides a **保存** operation to save all registered ticket types and prices for the Production.

Conceptually:

```text
Ticket types / prices
↓
保存
↓
Production's common ticket configuration is updated
```

The exact API persistence model, validation messages, duplicate handling, and error handling are implementation details unless separately confirmed.

---

## 7. Relationship to Performance

Ticket configuration belongs to the Production and is common across its Performances.

The confirmed structure is:

```text
Production
├── Member Management
├── Rehearsal Management
├── Performance Management
└── Ticket Management
      ↓
    Reception / Check-in
```

Performance Management registers the individual Performance occurrences.

Ticket Management registers the ticket types and prices shared by the Production.

Reception / check-in operates against the reservation/ticket list for a selected individual Performance.

The fact that reception is performed per Performance does **not** mean that ticket type/price configuration is performed per Performance.

---

## 8. Example

A Production has three Performances:

```text
2026/10/10 14:00
2026/10/10 18:00
2026/10/11 14:00
```

The common Ticket Management screen may contain:

```text
一般              2,000円
学生              1,000円
（M/D日分）一般   1,500円
```

These ticket definitions belong to the Production.

If a special price needs to be identified as applying to a particular date/performance, that information can be included in the ticket type name, such as **（M/D日分）一般**.

The system does not introduce a separate per-Performance ticket pricing configuration merely to represent this case.

---

## 9. Confirmed Scope

The following are confirmed requirements for this screen:

- Ticket management is configured at the **Production level**.
- Ticket configuration is **common across the Production's Performances**.
- Each ticket entry consists of **チケット種別 + 金額**.
- Multiple ticket entries can be registered.
- A **＋** button adds another ticket type/price row.
- No separate **備考** field is provided.
- Performance-specific discounts or special pricing can be represented in the **チケット種別** text, e.g. **（M/D日分）一般**.
- A **保存** operation saves the ticket configuration.
- Reception / check-in remains an individual-Performance operation.

The following are intentionally not fixed by this specification:

- Exact row layout and dimensions.
- Exact currency input UI.
- Exact validation/error messages.
- Exact API endpoint and persistence implementation.
- Seat assignment or seat-map management.
- Reservation workflow details beyond existing specifications.
- Reception/check-in details beyond the existing reception specification.
- Automatic date/performance restriction logic based on text in チケット種別.

---

## 10. Implementation Rule

Do not implement ticket type/price configuration as a separate configuration for every Performance.

The confirmed business rule is:

```text
Production
↓
Common Ticket Types / Prices
```

while operational reception remains:

```text
Production
↓
Performance
↓
Reservation / Ticket List
↓
Reception / Check-in
```

The ticket type name is user-defined text and may contain information such as a date-specific discount label. The system must not infer additional business rules from that text unless separately specified.

This screen specification supplements the StageArt Blueprint and is authoritative for the confirmed Ticket Management screen UX described above.
