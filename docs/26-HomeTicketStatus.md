# StageArt Blueprint
# Chapter 26 : Home Screen — Ticket Quota and Ticket Back Status

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This chapter supplements the Home screen specification in Chapter 12.

**This is not a separate Person Information screen.** The information defined here is displayed on the user's existing **Home screen** as information relevant to that Person.

The Home screen provides the user with their current ticket-related status for Productions in which they participate.

---

## 2. Ticket Quota Display

The Home screen displays the person's ticket quota progress using the following form:

```text
予約枚数 / ノルマ枚数
```

The **予約枚数** is the number of tickets reserved with the person as the Production's **扱い**.

The **ノルマ枚数** is the quota applicable to the person for the relevant Production.

Example:

```text
チケットノルマ：8 / 20枚
```

The displayed progress is based on reservation quantity, not actual sales amount or actual attendance.

---

## 3. Ticket Back Estimate Display

The Home screen displays the person's **チケットバック金額見込み**.

The estimate is calculated from **ticket reservation quantity**.

It is not calculated from:

- actual sales amount; or
- final actual attendance.

The estimate uses:

- ticket quantity in reservations associated with the person as **扱い**;
- the Production's common チケットバック calculation method; and
- the Production's configured multiple ticket-back conditions and rates.

The displayed amount is a current estimate and is distinct from the final チケットバック amount.

---

## 4. Relationship with Final Ticket Back

The Home screen estimate and the final settlement amount are separate concepts.

```text
予約枚数
 ↓
Home
 ├─ ノルマ：予約枚数 / ノルマ枚数
 └─ チケットバック金額見込み

公演終了
 ↓
実来場枚数
 ↓
チケットバック確定額
 ↓
未払い金
 ↓
精算
 ↓
未払い金 = 0円
```

The final チケットバック amount is calculated from actual attendance according to Chapter 25.

The Home screen's チケットバック金額見込み is calculated from reservation quantity before the final attendance-based calculation is made.

---

## 5. Production Context

The Home screen may display ticket-related information for Productions to which the Person is attached and for which relevant ticket quota / ticket-back information exists.

The Production's ticket-back rules are configured at the Production level and are not individually configured on the Home screen.

The person's reservation quantity is determined from reservations whose **扱い** identifies that person's Production participation/member record.

---

## 6. Confirmed Scope

The following are confirmed:

- The ticket quota and ticket-back estimate are displayed on the existing **Home screen**.
- There is no separate "Person Information Screen" for this purpose.
- ノルマ is displayed as **予約枚数 / ノルマ枚数**.
- The ticket-back estimate is based on **予約枚数**.
- The estimate is not based on actual sales amount.
- The estimate is not the final attendance-based ticket-back amount.
- The Production's common ticket-back method and conditions are used for the estimate.
- Production-wide ticket-back rules are not edited from Home.

---

## 7. Scope Not Yet Defined

This specification does not define:

- Exact Home screen layout.
- Exact wording beyond the confirmed **予約枚数 / ノルマ枚数** representation.
- How the quota itself is configured in Production management.
- Detailed quota rules beyond the confirmed reservation-count display.
- Detailed ticket-back rounding rules.
- Detailed ticket-type-specific calculation rules.
- Settlement operation/history UI.

These items must be confirmed separately before implementation.
