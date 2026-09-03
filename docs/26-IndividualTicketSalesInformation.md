# StageArt Blueprint
# Chapter 26 : Individual Ticket Sales Information

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the information that a StageArt user can view on their individual page concerning Production ticket sales management.

The individual page is the user's own view of their Production-related ticket sales information. It is not the place where Production-wide チケットバック rules are configured.

---

## 2. Information Displayed

The individual page must display, at minimum:

- **ノルマ**
- **チケットバック金額見込み**

These values are displayed in the context of the Productions in which the user participates and for which the relevant ticket sales management information applies.

---

## 3. チケットバック金額見込み

**チケットバック金額見込み** represents an expected/forecast amount before the final チケットバック amount is determined from actual attendance.

The forecast is distinct from the final チケットバック amount and the resulting 未払い金.

The exact calculation timing and detailed formula for the forecast are not fixed by this specification.

---

## 4. Final Amount and Unpaid Amount

The final チケットバック amount is calculated based on actual attendance according to the Production's ticket-back rules.

After the final amount is determined, the amount owed to the individual is retained as **未払い金**.

The individual page may therefore present the final amount/unpaid state as part of the user's own ticket sales information, but the minimum confirmed display requirement is:

- ノルマ
- チケットバック金額見込み

The exact presentation of final amount, unpaid amount, settlement status, and settlement history is not fixed here unless separately confirmed.

---

## 5. Relationship with Production Ticket Back Rules

The Production defines the common ticket-back settings:

- Calculation method: 累進方式 / 分離方式
- Multiple sales-quantity conditions
- Ticket-back rate for each condition

The individual page does not define or override these rules.

The user's ticket sales count is derived from reservations associated with the user as **扱い**, and the Production's common rules are applied to that count. Final チケットバック is based on actual attendance.

Conceptually:

```text
Production
├─ チケットバック設定
│    ├─ 累進方式 / 分離方式
│    └─ 複数条件
│
└─ Production Member
     └─ 扱いとして紐づく予約
            ↓
        実際の来場
            ↓
        チケットバック確定
            ↓
          未払い金

Individual Page
↓
ノルマ
チケットバック金額見込み
```

---

## 6. Confirmed Scope

- The individual page displays the user's **ノルマ**.
- The individual page displays the user's **チケットバック金額見込み**.
- The forecast amount is distinct from the final amount calculated from actual attendance.
- Production-wide ticket-back rules are configured at the Production level, not on the individual page.
- The exact forecast formula and display details are intentionally not fixed here.
