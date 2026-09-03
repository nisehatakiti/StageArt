# StageArt Blueprint
# Chapter 26 : Person Information Screen — Ticket Quota and Ticket Back

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This chapter defines the information that a Person can view on their individual page concerning Production ticket sales, **ノルマ**, and **チケットバック**.

The individual page provides the person with a view of their current ticket-related status without requiring them to enter Production management screens.

---

## 2. Ticket Quota Display

The individual page displays the person's ticket quota progress using the following form:

```text
予約枚数 / ノルマ枚数
```

The **予約枚数** is the number of tickets reserved with the person as the Production's **扱い**.

The **ノルマ枚数** is the quota configured for the relevant Production/member context.

Example:

```text
チケットノルマ：8 / 20枚
```

The display represents the current reservation count against the quota. It is not based on actual ticket sales amount.

The exact input/configuration model for the quota itself remains outside this chapter unless separately confirmed.

---

## 3. Ticket Back Estimate Display

The individual page displays the person's **チケットバック金額見込み**.

The estimate is calculated using **ticket reservation quantity**, not actual sales amount or final attendance.

The calculation uses:

- Reservation ticket quantity associated with the person as **扱い**.
- The Production's common チケットバック calculation method.
- The Production's configured multiple ticket-back conditions and rates.

The displayed amount is therefore a **current estimate based on reservation quantity**.

It is not the final チケットバック amount.

---

## 4. Relationship with Final Ticket Back

The individual-page estimate is distinct from the final チケットバック amount.

```text
予約枚数
 ↓
個人ページ
 ├─ ノルマ：予約枚数 / ノルマ枚数
 └─ チケットバック金額見込み

公演終了
 ↓
実来場枚数
 ↓
チケットバック確定額
 ↓
未払い金
```

The final チケットバック amount is calculated from actual attendance according to Chapter 25. The individual page's **チケットバック金額見込み** is based on reservation quantity before final attendance is established.

---

## 5. Production Context

The individual page may show ticket-related information for Productions in which the Person participates and has relevant ticket sales/handling information.

The Production's チケットバック rules are common to the Production and are not individually configured on the Person page.

The person's reservation count is determined from reservations whose **扱い** identifies that Person's Production participation/member record.

---

## 6. Confirmed Scope

The following are confirmed:

- The individual page displays **ノルマ** information.
- ノルマ is represented as **予約枚数 / ノルマ枚数**.
- The individual page displays **チケットバック金額見込み**.
- チケットバック金額見込み is based on **予約枚数**, not actual sales amount.
- The estimate uses the Production's common ticket-back calculation method and conditions.
- Final チケットバック is based on actual attendance and is distinct from the estimate.
- The individual page does not define or edit the Production-wide ticket-back rules.

---

## 7. Scope Not Yet Defined

This specification does not define:

- Exact page layout.
- Exact wording/formatting beyond the confirmed **予約枚数 / ノルマ枚数** representation.
- How a Production quota is configured in the Production management screen.
- Detailed quota calculation rules beyond the confirmed reservation-count display.
- Detailed ticket-back rounding rules.
- Detailed ticket-type-specific calculation rules.
- Settlement operation/history.

These items must be confirmed separately before implementation.
