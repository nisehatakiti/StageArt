# StageArt Blueprint
# Chapter 29 : Production Settlement Screen Specification

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the Production-level **精算** screen used to settle confirmed ticket-back unpaid amounts for Production members.

Settlement is performed individually for each member.

---

## 2. Production Management Menu

The Production management screen adds the following menu:

- 精算

Conceptually:

```text
公演管理
├─ メンバー管理
├─ 稽古管理
├─ 公演回管理
├─ チケット管理
├─ ノルマ管理
├─ チケットバック管理
├─ 精算
└─ 公演HP / 情報管理
```

The menu is available to users who have the corresponding Production management authority, according to the existing Production management authorization rules.

---

## 3. Settlement Screen

The screen displays a list of Production members and their confirmed ticket-back unpaid amounts.

Conceptually:

```text
精算

メンバー名        チケットバック未払い金     状態
------------------------------------------------
山田 太郎              24,000円              未精算
佐藤 花子              12,000円              未精算
鈴木 一郎               0円                  精算済み

[精算済みにする]   ← 個人ごと
```

The exact table columns and visual layout are implementation/UX details unless separately confirmed.

---

## 4. Settlement Operation

Each member can be settled independently.

For an individual member whose confirmed ticket-back amount remains unpaid, the manager can perform the **精算済み** operation.

After settlement:

```text
対象メンバーのチケットバック未払い金
↓
0円
↓
精算済み
```

Settling one member must not settle any other member.

---

## 5. Settlement Eligibility

The settlement list is based on the Production's confirmed individual ticket-back amounts.

The amount to be settled is the confirmed **未払い金**, not the Home ticket-back estimate.

Home displays an estimate based on reservation quantity, while settlement uses the final ticket-back amount calculated from actual checked-in attendance.

---

## 6. Relationship to Ticket Back

The overall flow is:

```text
Reservation
↓
扱いによる個人別予約枚数
↓
Home：チケットバック見込み

Performance終了
↓
Reception / Check-in
↓
実来場枚数（CHECKED_INのReservationのGuest Count合計）を
扱い（メンバー）ごと・チケット種別ごとに集計
↓
Production共通のチケットバック条件・計算方式を適用
（1枚あたりの単価 = 各ReservationのPrice Snapshot）
↓
個人別チケットバック確定額
↓
個人別未払い金
↓
公演管理「精算」
↓
個人ごとに精算済みにする
↓
未払い金 = 0円
```

---

## 7. Confirmed Rules

- **精算** is a Production-level management menu.
- The screen displays Production members and their confirmed ticket-back unpaid amounts.
- Settlement is performed **one member at a time**.
- A member can be marked **精算済み** independently of other members.
- Settlement changes the applicable ticket-back unpaid amount to **0円**.
- Settlement uses the final ticket-back amount, not the Home estimate.
- The final ticket-back amount is based on actual tickets checked in at Reception / Check-in.
- Ticket-back calculation is performed separately by ticket type, using each Reservation's booking-time unit price (`Reservation.priceSnapshot`), not the ticket's current price.

### 7.1 Ticket-back quantity and unit price (confirmed 2026-11-10, PR #7)

**Quantity**

- Only seats that actually came in count: Reservations whose status is **CHECKED_IN**.
- Quantity is the sum of `guestCount`, not the number of Reservations. One CHECKED_IN Reservation with `guestCount = 3` counts as **3**.
- **NO_SHOW**, **CANCELLED** and still-**RESERVED** Reservations count as **0**.
- Seats are totalled per member by the Reservation's 扱い (`attributedPersonId`). A Reservation without 扱い belongs to no member.
- When the Production has more than one ticket type, seats are totalled per ticket type. The Production-wide conditions are applied to each type's own quantity, and the member's amounts for all types are added together.

**Unit price**

- Each seat is valued at its Reservation's `priceSnapshot`, the ticket price fixed when the Reservation was made.
- The current `Ticket.price` is never used for past Reservations. Changing a ticket's price after booking does not change the ticket-back amount for Reservations made before the change.
- 累進方式: the rate of the condition reached by the type's quantity is applied to the sum of the seats' `priceSnapshot`.
- 分離方式: seats of one ticket type are taken in booking order (Reservation creation time). Each seat falls into the band its position reaches, at its own `priceSnapshot`. A band from 0枚 starts at the first seat.

Example. The rule is 累進方式, 1枚以上 10%. A member's Reservation has `guestCount = 3` and was booked at 3,000円; the ticket was later changed to 3,500円.

| Status | Ticket-back quantity | Ticket-back amount |
|---|---|---|
| CHECKED_IN | 3 | 3 × 3,000円 × 10% = 900円 |
| NO_SHOW | 0 | 0円 |
| CANCELLED | 0 | 0円 |

**Separate from the Quota**

The Production-wide Quota (ノルマ) uses its own sales-performance count, which is not part of this rule. That count is CHECKED_IN + NO_SHOW, counted in Reservations, and is unchanged.

### 7.2 Over-payment after a refund (2026-11-10, PR #7 R7)

- A Check-in reversed (refunded) after the member was settled lowers the confirmed amount, while the settled amount stays. The difference is shown on the member's row as **払い過ぎ: N円**.
- Production Completion (決算完了) is refused while any member is over-paid, as it is while any member is under-settled (`docs/04-DomainModel/ProductionLifecycle.md` 「決算完了の精算条件」).
- With today's operations, the manager resolves it before completion by cancelling the member's last settlement and settling again at the confirmed amount.
- Open issue: cancelling undoes only the last settlement, once. A member settled in several steps who then becomes over-paid by more than the last step cannot be brought back to the confirmed amount, so the Production cannot be completed. A settlement adjustment (reduction / refund record) is needed; not decided.

---

## 8. Scope

The following are not fixed by this specification unless separately confirmed:

- Actual payment method.
- Whether payment is cash, bank transfer, or another method.
- Accounting journal details.
- Detailed settlement history/audit UI.
- Receipt generation.
- Settlement reversal/correction procedure.
- Exact table columns and visual styling.
