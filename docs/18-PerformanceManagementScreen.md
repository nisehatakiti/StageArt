# StageArt Blueprint
# Chapter 18 : Performance Management Screen Specification

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the confirmed screen specification for managing individual Performances (公演回) belonging to a Production.

A Performance is a specific occurrence of a Production. Performance management is therefore subordinate to Production and is a sibling operation to Production member management and Production rehearsal management.

The business hierarchy is:

Organization
↓
Production
├── Member Management
├── Rehearsal Management
└── Performance Management
      ↓
    Ticket Management
      ↓
    Reception / Check-in

Performance management must not be treated as the parent of Production member management or Rehearsal Management.

---

## 2. Performance Management Screen

The screen is entered from the Production information/management context through **公演回管理**.

The purpose of the screen is to register and edit the multiple Performance occurrences belonging to the Production, together with common venue-opening information.

---

## 3. Performance Registration

A Production can have one or more Performances.

Each Performance is registered using the following pair of fields:

- **公演日付**
- **開演時間**

The pair represents one Performance occurrence.

### Multiple Performances

The screen must allow multiple Performance occurrences to be registered on the same screen.

The initial screen provides one set of:

```text
公演日付    開演時間
[        ]  [        ]
```

A **＋** button is provided to add another Performance field set.

Conceptually:

```text
公演日付        開演時間
[2026/10/10]    [14:00]

公演日付        開演時間
[2026/10/10]    [18:00]

公演日付        開演時間
[2026/10/11]    [14:00]

        ＋ 公演回を追加
```

Each added field set represents one independent Performance.

A remove operation may be provided for an existing field set as an implementation/UX detail, but the confirmed requirement is that the **＋** operation can add additional Performance field sets.

---

## 4. Common Opening Information

The screen also provides a common **開場情報** field for the Production's Performances.

This is a free-text field.

Example:

```text
開場情報
[ 開場は開演の30分前です。                         ]
```

The text is common information for the Performances and is not entered separately for each Performance.

The user may enter arbitrary explanatory text rather than being restricted to a numeric "minutes before opening" field.

For example:

> 開場は開演の30分前です。

is a valid value.

The exact wording is determined by the user.

---

## 5. Save

The screen provides a **保存** operation to save the registered Performance occurrences and common opening information.

Conceptually:

```text
Performance dates / start times
+
Common opening information
↓
保存
↓
Production's Performance information is updated
```

The exact API persistence model, validation messages, and error handling are implementation details unless separately confirmed.

---

## 6. Relationship to Production

Performance management belongs under Production.

The confirmed Production structure is:

```text
Production
├── 公演情報
├── メンバー管理
├── 稽古管理
└── 公演回管理
      ↓
    チケット管理
      ↓
    受付
```

Therefore:

- Production member management is not subordinate to Performance.
- Production rehearsal management is not subordinate to Performance.
- Performance management is a sibling operation to those Production-level operations.
- Ticket management is subordinate to an individual Performance.
- Reception / check-in is performed for an individual Performance.

---

## 7. Navigation / Context

The normal user flow is:

Production information
↓
公演回管理
↓
Select or manage a Performance
↓
チケット管理
↓
受付

The exact visual navigation differs between Web and Mobile as appropriate, but the business hierarchy must remain the same.

---

## 8. Confirmed Scope

The following are confirmed requirements for this screen:

- Performance management is under Production.
- A Production can have multiple Performances.
- Each Performance is defined by a **公演日付 + 開演時間** pair.
- The user can add additional Performance pairs using a **＋** button.
- A common **開場情報** free-text field is provided for the Production's Performances.
- The common opening information is entered once rather than separately for each Performance.
- The screen provides a save operation.
- Ticket management remains subordinate to individual Performances.

The following are intentionally not fixed by this specification:

- Exact date/time picker UI.
- Exact layout dimensions.
- Exact validation/error messages.
- Exact API endpoint and persistence implementation.
- Performance status/lifecycle rules beyond what is defined by the applicable Domain specification.
- Ticket types, seat management, reservations, and reception details beyond their existing specifications.

---

## 9. Implementation Rule

When implementing Performance management, do not introduce a new business hierarchy that places Production members or rehearsals below Performance.

The confirmed hierarchy is:

```text
Organization
↓
Production
├── Member Management
├── Rehearsal Management
└── Performance Management
      ↓
    Ticket Management
      ↓
    Reception / Check-in
```

This screen specification supplements Chapter 12 of the StageArt Blueprint and is authoritative for the confirmed Performance Management screen UX described above.
