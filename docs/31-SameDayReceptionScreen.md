# StageArt Blueprint
# Chapter 31 : Same-Day Reception Screen Specification

Version : 1.1
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
チケット種別     [ 選択 ▼ ]
枚数             [ 選択 ▼ ]

料金             ￥自動計算

[確認]
```

### 3.1 扱い

- Same-day reception does **not** require or record **扱い**.
- The added same-day reservation is therefore not attributed to a Production participant for quota or ticket-back purposes.

### 3.2 チケット種別

- The operator selects the applicable **当日券の価格設定**.
- If a day-ticket price setting is available, that configured price is used for the transaction amount.
- The selected ticket type/price is recorded as part of the same-day reservation.

### 3.3 枚数

- The operator selects the ticket quantity using a pull-down list.

### 3.4 料金

- The system automatically calculates and displays the transaction amount based on the selected day-ticket price and quantity.
- The calculated amount is acceptable as the reservation transaction amount; it does not need to be manually entered.

---

## 4. Confirmation Flow

Pressing **確認** does not immediately finalize the same-day reception.

The flow is:

```text
当日受付
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
残席チェック
↓
予約に当該行を1行追加
↓
追加された予約をCheckIn済みとして扱う
```

The reservation addition and Check In completion are part of the same confirmed same-day reception operation.

---

## 5. Capacity Check and Forced Reception

Same-day reception performs a **Performance-level remaining-capacity check**.

- If sufficient capacity remains, the reception can be registered normally.
- If the requested quantity exceeds the remaining capacity, the system displays a **remaining-capacity alert**.
- A capacity alert does **not** necessarily prevent registration.
- The operator may either refuse the guest because there is insufficient capacity, or, when the venue can accept the additional guests, proceed with a **forced registration**.
- Forced registration allows the reception to be added even when the configured Performance capacity would otherwise be exceeded.

The exact alert wording and forced-registration confirmation wording are implementation/UI details and are not fixed by this chapter.

---

## 6. Relationship to Ticket Sales Period

Same-day reception is permitted regardless of the normal ticket sales end setting.

In particular:

- The Production ticket sales start/end rules used for normal reservations do not prevent same-day reception.
- Same-day reception remains available after normal ticket sales have ended.
- Same-day reception is intended for reception on the day of the Performance and may therefore be performed after normal advance ticket sales have closed.

---

## 7. Reservation Creation

When the operator presses **OK** on the confirmation screen and the reception is accepted:

- One row corresponding to the entered same-day reception ticket content is added to the target Performance's reservations.
- The calculated transaction amount is stored with the reservation.
- A reservation number is generated internally for the created reservation.
- The added reservation is immediately treated as **CheckIn済み**.

Same-day reception therefore does not require a separate subsequent reception/check-in operation for the newly added reservation.

---

## 8. Reservationer Information

Same-day reception does **not** require the following information:

- Reservationer's name
- Email address
- Telephone number
- Other contact information
- 扱い

The confirmed same-day reception input is based on:

- チケット種別 / 当日券価格設定
- 枚数
- Automatically calculated 料金

No reservationer/contact information or 扱い information is required for the same-day reception flow.

---

## 9. Relationship to Check In

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
残席チェック
↓
Reservation 1行追加
↓
Check In済み
```

The same-day reception must therefore produce the same business result of a checked-in reservation while avoiding a separate manual selection of the newly created reservation.

---

## 10. Relationship to 扱い / Quota / Ticket Back

Same-day reception has **no 扱い**.

Therefore, the added same-day reception reservation:

- Is not attributed to a Production participant's 扱い.
- Does not increase a member's quota progress.
- Does not contribute to member-level ticket-back calculations based on 扱い.
- Is nevertheless a confirmed **Check In** and is included in the actual attendance/check-in record for the Performance.

---

## 11. Changes and Cancellation

A same-day reception reservation does **not** support subsequent change or cancellation processing.

This is because the same-day reception represents a guest who has already arrived at the venue and been admitted.

No change/cancellation flow is required for same-day reception.

---

## 12. Confirmed Requirements

The following are confirmed requirements:

- A dedicated **当日受付** button is provided on the reception table.
- Pressing the button opens a same-day reception input screen.
- The input screen contains **チケット種別**.
- The applicable **当日券の価格設定** can be selected and used for the transaction amount.
- The input screen contains **枚数** as a pull-down selection.
- The amount is automatically calculated from the selected day-ticket price and quantity.
- **扱い** is not required and is not recorded.
- **確認** opens a confirmation screen.
- Pressing **OK** on the confirmation screen finalizes the operation.
- A Performance-level remaining-capacity check is performed.
- If capacity is insufficient, a remaining-capacity alert is displayed.
- Capacity insufficiency does not necessarily prohibit registration; forced registration is possible when the operator decides to accept the additional guests.
- Same-day reception is allowed even after normal ticket sales have ended.
- Finalization adds one corresponding row to the target Performance's reservations.
- The calculated transaction amount is stored with the reservation.
- An internal reservation number is generated for the created reservation.
- The added reservation is immediately treated as **CheckIn済み**.
- Same-day reception does not require reservationer name or contact information.
- Same-day reception does not participate in **扱い**, quota progress, or member-level ticket-back calculations.
- Same-day reception does not require subsequent change/cancellation processing.

The following remain implementation/UI details unless separately confirmed:

- Exact pull-down option ranges for 枚数.
- Exact confirmation-screen wording.
- Exact capacity alert wording.
- Exact forced-registration confirmation wording.
- Exact row display details in the reception table.
- Exact format of the internally generated reservation number.
- Exact persistence/API implementation.
