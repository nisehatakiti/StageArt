# StageArt Blueprint
# Chapter 28 : Ticket Back Management Screen Specification

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the Production-level **チケットバック管理** screen and the business rules for configuring Production-wide ticket-back calculation.

Ticket-back settings are shared across the Production and are applied to each Production member's ticket count derived from reservations whose **扱い** is that member.

---

## 2. Production Management Menu

The Production management screen adds the following menu:

- チケットバック管理

The menu is available to users who have the corresponding Production management authority, according to the existing Production management authorization rules.

Conceptually:

```text
公演管理
├─ メンバー管理
├─ 稽古管理
├─ 公演回管理
├─ チケット管理
├─ ノルマ管理
├─ チケットバック管理
└─ 公演HP / 情報管理
```

チケットバック管理 is a Production-level management operation. It is not configured separately for individual members or Performances.

---

## 3. Ticket Back Management Screen

The screen provides the following settings and operations.

```text
チケットバック管理

計算方式　[ 累進方式 ▼ ]

【計算方式の説明】
累進方式：到達した条件の料率を、それまでの販売枚数全体に適用
分離方式：販売枚数を条件ごとの段階に分け、それぞれの料率を適用

条件
優先順位　枚数　条件　料率
    1　　 10　 [以上▼] [20] %
    2　　 20　 [以上▼] [40] %

[＋条件を追加する]

[ 保存 ]
```

---

## 4. Calculation Method

The user selects the ticket-back calculation method from a pull-down list.

Available values are:

- **累進方式**
- **分離方式**

The screen must display an explanation of both methods so that the administrator can understand the difference before saving.

### 4.1 累進方式

**累進方式** applies the rate of the highest-priority/reached applicable condition to the entire relevant ticket quantity.

For example:

```text
10枚以上 → 20%
20枚以上 → 40%
```

For 25枚, the 20枚以上 condition is reached, so 40% is applied to all 25枚.

```text
チケット代 × 25枚 × 40%
```

### 4.2 分離方式

**分離方式** divides the relevant ticket quantity into the configured stages and applies each stage's rate to the corresponding quantity.

For example:

```text
10枚以上 → 20%
20枚以上 → 40%
```

For 25枚:

```text
チケット代 ×（10枚 × 20% ＋ 15枚 × 40%）
```

Thus, the same ticket quantity and conditions can produce different ticket-back amounts depending on the selected calculation method.

---

## 5. Conditions

The screen allows multiple ticket-back conditions to be registered using **「＋条件を追加する」**.

Each condition consists of the following fields:

- **優先順位**
- **枚数**
- **条件**
- **料率（%）**

### 5.1 優先順位

Each condition has a priority order.

Conditions are calculated/applied **in order from the highest priority to the lower priority**.

The priority is part of the registered condition set and determines the order in which conditions are evaluated.

### 5.2 枚数

Enter the ticket quantity associated with the condition.

### 5.3 条件

Select one of the following from a pull-down list:

- **未満**
- **以下**
- **以上**

The selected comparison operator is applied to the configured ticket quantity when determining whether the condition applies.

### 5.4 料率

Enter the ticket-back rate as a percentage.

Example:

```text
料率：[20] %
```

---

## 6. Multiple Conditions

The screen must allow multiple conditions to be registered.

Example:

| 優先順位 | 枚数 | 条件 | 料率 |
|---:|---:|---|---:|
| 1 | 10 | 以上 | 20% |
| 2 | 20 | 以上 | 40% |

The user can add further conditions using:

```text
＋条件を追加する
```

The exact maximum number of conditions is not fixed by this specification.

---

## 7. Condition Processing Order

Conditions are processed from the **highest priority to the lower priority**.

The registered priority therefore has business meaning and must not be treated as merely a display order.

The exact internal algorithm for resolving overlapping or mutually compatible conditions beyond this priority rule is not separately specified here. Implementation must preserve the confirmed priority ordering and the selected comparison operator for each condition.

---

## 8. Relationship to Production Members

Ticket-back settings are **Production-wide**.

The screen does not provide a ticket-back rate or calculation method for each individual member.

The relationship is:

```text
Production
└─ Ticket Back Settings
     ├─ Calculation Method
     └─ Conditions (multiple)

Production Member
└─ Reservations whose 扱い is that member
      ↓
   Ticket quantity
      ↓
Production-wide Ticket Back Settings
      ↓
Ticket Back amount
```

A member's current ticket-back estimate shown on Home is calculated from reservation quantity, not actual sales amount, using these Production-wide settings.

The final ticket-back amount after the Performance is based on actual attendance according to the existing Production Ticket Sales Rules.

---

## 9. Save

The screen provides a **「保存」** operation.

Saving updates the Production's ticket-back settings, including:

- Calculation method
- All registered conditions
- Each condition's priority
- Each condition's ticket quantity
- Each condition's comparison operator
- Each condition's ticket-back rate

The settings apply to the Production as a whole.

---

## 10. Confirmed UX and Business Rules

- **チケットバック管理** is a Production-level management menu.
- Ticket-back settings are Production-wide and are not configured per member or per Performance.
- The calculation method is selected from **累進方式 / 分離方式**.
- The screen explains the difference between 累進方式 and 分離方式.
- Multiple conditions can be registered using **「＋条件を追加する」**.
- Each condition contains **優先順位 / 枚数 / 条件 / 料率（%）**.
- 条件 is selected from **未満 / 以下 / 以上**.
- Conditions are processed from the highest priority to the lower priority.
- The ticket-back rate is entered as a percentage.
- **保存** saves the Production-wide ticket-back settings.
- Home ticket-back estimates continue to use reservation quantity, not actual sales amount.
- Final ticket-back calculation continues to use actual attendance according to Chapter 25.

---

## 11. Relationship to Existing Blueprint

This chapter supplements and provides the detailed screen UX for the Production-wide ticket-back settings already established in:

- Chapter 19 : Ticket Management Screen Specification
- Chapter 25 : Production Ticket Sales Rules — Quota and Ticket Back

Those chapters establish that ticket-back is a Production-wide setting, with **累進方式 / 分離方式** and multiple sales-quantity conditions. This chapter defines the confirmed dedicated management screen and the condition fields/priority behavior.

The existing business rules for reservation-based estimates, actual-attendance-based final calculation, unpaid amounts, and settlement remain unchanged.

---

## 12. Scope

This chapter defines the confirmed business behavior and primary UX for Production Ticket Back Management.

Detailed API paths, database field names, validation messages, authorization implementation, exact input constraints, maximum condition count, and visual styling are implementation details unless separately confirmed.
