# StageArt Blueprint
# Chapter 31 : Same-Day Reception Screen Specification

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the confirmed business behavior and primary UX for **当日受付** (same-day / walk-in reception) performed from the reception/check-in operation for an individual Performance.

当日受付は、通常の予約済みReservationを検索してCheck Inする受付方法とは異なり、受付時点で必要なチケット内容を入力し、確認・確定と同時に予約を追加してCheck In済みとする専用フローである。

---

## 2. Entry Point

The reception table for the selected Performance provides a dedicated:

```text
[当日受付]
```

button.

Pressing **当日受付** opens the same-day reception input screen.

---

## 3. Same-Day Reception Input Screen

The input screen contains the following fields:

```text
誰扱い          [ 選択 ▼ ]
チケット種別     [ 選択 ▼ ]
枚数             [ 選択 ▼ ]

料金             ￥自動計算

[確認]
```

### 3.1 誰扱い

- The operator selects **誰扱い** from the Production participants available for the target Production.
- The selected person is recorded as the handling person (**扱い**) for the reservation created by the same-day reception.
- The same-day reception therefore retains the attribution required for Production member ticket-count aggregation, quota, and ticket-back rules.

### 3.2 チケット種別

- The operator selects a ticket type from the ticket types configured for the Production.
- Ticket types and their configured prices are managed at Production level and are common across the Production's Performances.

### 3.3 枚数

- The operator selects the ticket quantity using a pull-down list.

### 3.4 料金

- The system automatically calculates and displays the amount based on the selected ticket type and quantity.
- The calculation uses the configured ticket type price and the selected quantity.

---

## 4. Confirmation Flow

Pressing **確認** does not immediately finalize the same-day reception.

The flow is:

```text
当日受付
↓
誰扱いを選択
↓
チケット種別を選択
↓
枚数を選択
↓
料金を自動計算・表示
↓
[確認]
↓
確認画面
↓
OK
↓
予約に当該行を1行追加
↓
追加された予約をCheckIn済みとして扱う
```

The reservation addition and Check In completion are part of the same confirmed same-day reception operation.

---

## 5. Reservation Creation

When the operator presses **OK** on the confirmation screen:

- One row corresponding to the entered same-day reception ticket content is added to the target Performance's reservations.
- The added reservation records the selected **誰扱い**.
- The added reservation is immediately treated as **CheckIn済み**.

Same-day reception therefore does not require a separate subsequent reception/check-in operation for the newly added reservation.

---

## 6. Reservationer Information

Same-day reception does **not** require the following information:

- Reservationer's name
- Email address
- Telephone number
- Other contact information

The confirmed same-day reception input is based on:

- 誰扱い
- チケット種別
- 枚数
- Automatically calculated 料金

No additional reservationer/contact fields are required by this specification.

---

## 7. Relationship to Check In

The normal Check In domain represents the fact that an existing Reservation has completed reception.

Same-day reception is a dedicated creation flow in which the Reservation is added and immediately treated as checked in when the operator confirms the operation.

Conceptually:

```text
通常受付
Reservation
↓
Check In
↓
CHECKED_IN

当日受付
入力
↓
確認
↓
Reservation 1行追加
↓
Check In済み
```

The same-day reception must therefore produce the same business result of a checked-in reservation while avoiding a separate manual selection of the newly created reservation.

---

## 8. Relationship to 扱い / Quota / Ticket Back

Because the same-day reception records **誰扱い** on the added reservation, the added ticket quantity is attributable to that Production participant's **扱い**.

Accordingly, the same-day reception reservation participates in the existing rules for:

- Reservation quantity aggregation by 扱い
- ノルマ progress
- Ticket-back preview based on reservation quantity
- Final ticket-back calculation based on actual Check In

The same-day reception is already CheckIn済み when confirmed, so the added quantity is also included in the actual check-in basis for the relevant ticket-back rules.

---

## 9. Scope

The following are confirmed requirements:

- A dedicated **当日受付** button is provided on the reception table.
- Pressing the button opens a same-day reception input screen.
- The input screen contains **誰扱い**.
- The input screen contains **チケット種別** as a pull-down selection.
- The input screen contains **枚数** as a pull-down selection.
- The amount is automatically calculated and displayed from ticket type and quantity.
- **確認** opens a confirmation screen.
- Pressing **OK** on the confirmation screen finalizes the operation.
- Finalization adds one corresponding row to the target Performance's reservations.
- The added reservation records the selected **誰扱い**.
- The added reservation is immediately treated as **CheckIn済み**.
- Same-day reception does not require reservationer name or contact information.
- The added reservation participates in the existing **扱い**, quota, and ticket-back rules.

The following are intentionally not fixed by this chapter unless separately confirmed:

- Exact pull-down option ranges for 枚数.
- Exact confirmation-screen wording.
- Exact validation/error messages.
- Exact row display details in the reception table.
- Exact reservation number generation/display for the same-day reservation.
- Exact persistence/API implementation.
- Any additional fields not explicitly confirmed above.
