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
実来場枚数をチケット種別ごとに集計
↓
Production共通のチケットバック条件・計算方式を適用
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
- Ticket-back calculation is performed separately by ticket type using the Production-configured ticket price.

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
