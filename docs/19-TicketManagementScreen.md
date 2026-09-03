# StageArt Blueprint
# Chapter 19 : Ticket Management Screen Specification

Version : 1.2
Status : Confirmed business specification

---

## 1. Purpose

This document defines the confirmed screen specification for managing ticket types, prices, ticket sales end settings, and ticket-back settings for a Production.

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

The purpose of the screen is to register and edit the ticket types and their prices used for the Production, configure the Production-wide ticket-back rules, and configure when ticket reservations close for each Performance.

Ticket type and price configuration is **common across the Production's Performances**.

The ticket-back configuration is also **common across the Production** and is not configured separately for each member or Performance.

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

Ticket quantities are **aggregated separately for each ticket type**. Ticket-back calculation does not combine different ticket types into a single quantity.

---

## 5. Ticket Sales End Setting

The Production-level Ticket Management screen provides a **販売終了** setting.

The setting is expressed relative to each Performance as:

```text
販売終了
公演回の [   ]日前の [   ]時まで
```

For example:

```text
公演回の [1]日前の [23]時まで
```

means that ticket reservations for a Performance end at 23:00 on the day before that Performance.

The system calculates the ticket sales end date/time for each Performance from this Production-level setting.

The ticket sales end setting is common to the Production's Performances; it is not entered as a separate absolute date/time for every Performance.

The calculated sales end date/time is checked by the common ticket reservation flow when the user presses **予約**.

If the current date/time is past the calculated sales end date/time, the reservation cannot be accepted.

The exact input control, validation, and error message are implementation/UX details unless separately confirmed.

---

## 6. Ticket Back Settings

チケットバックは**Production（公演）全体**に対して設定する。

The screen must allow the following Production-level settings.

### 6.1 Calculation Method

The user can select one of two calculation methods:

- **累進方式**
- **分離方式**

### 6.2 Multiple Conditions

Multiple ticket-back conditions can be registered.

Each condition consists of:

- **販売枚数条件** — the ticket sales quantity at which the condition is reached
- **チケットバック率** — the percentage applied when the condition is applicable

Example:

| 販売枚数条件 | チケットバック率 |
|---:|---:|
| 10枚以上 | 20% |
| 20枚以上 | 40% |

Conditions use priority ordering, with priority 1 being the highest priority. The priority determines which condition is applicable; no additional condition-combination logic is required.

The screen must allow the user to add multiple condition rows.

The ticket-back conditions and calculation method are Production-wide settings. They are not configured individually for each Production member.

### 6.3 累進方式

**累進方式** means that the ticket-back rate corresponding to the highest reached condition is applied to the entire ticket sales quantity for the relevant ticket type.

For example:

- 10枚以上 → 20%
- 20枚以上 → 40%

If the relevant ticket sales count is 25枚:

```text
チケット代 × 25枚 × 40%
```

The 40% rate applies to all 25枚 because the 20枚以上 condition has been reached.

### 6.4 分離方式

**分離方式** means that the ticket sales quantity is divided according to the configured condition stages, and each stage is calculated using its corresponding ticket-back rate.

The same priority ordering is used when determining the applicable stages; no additional condition-combination logic is required.

For example:

- 10枚以上 → 20%
- 20枚以上 → 40%

If the relevant ticket sales count is 25枚:

```text
チケット代 ×（10枚 × 20% ＋ 15枚 × 40%）
```

Thus, the same sales quantity and conditions can produce different ticket-back amounts depending on the selected calculation method.

### 6.5 Condition Not Reached

If the first configured condition has not been reached, no ticket back is generated by that condition.

For example, when the only configured condition is:

```text
10枚以上 → 20%
```

a sales count of 9枚以下 does not generate a ticket back.

---

## 7. Performance-specific Discounts

Ticket types and prices are common across the Production rather than separately configured for each Performance.

When a discount or other special price applies to a particular Performance or date, the user expresses that distinction in the **チケット種別** text itself.

For example:

```text
（M/D日分）一般
```

may be registered as a ticket type.

The system therefore does not require a separate Performance-specific discount field, date-condition field, or ticket-price override field as part of this screen specification.

There is no separately confirmed StageArt discount mechanism. If a handling person personally discounts a ticket outside StageArt, that does not change the configured ticket type price or the ticket-back calculation price.

---

## 8. Save

The screen provides a **保存** operation to save all registered ticket types, prices, ticket sales end setting, and Production-level ticket-back settings.

Conceptually:

```text
Ticket types / prices
販売終了設定
Ticket Back calculation method / conditions
↓
保存
↓
Production's common ticket configuration is updated
```

The exact API persistence model, validation messages, duplicate handling, and error handling are implementation details unless separately confirmed.

---

## 9. Relationship to Performance

Ticket configuration belongs to the Production and is common across its Performances.

The confirmed structure is:

```text
Production
├── Member Management
├── Rehearsal Management
├── Performance Management
└── Ticket Management
      ├── Ticket Types / Prices
      ├── 販売終了
      └── Ticket Back Settings
            ↓
        Reception / Check-in
```

Performance Management registers the individual Performance occurrences.

Ticket Management registers the ticket types, prices, Production-wide ticket sales end setting, and Production-wide ticket-back rules.

Reception / check-in operates against the reservation/ticket list for a selected individual Performance.

The fact that reception is performed per Performance does **not** mean that ticket type/price or ticket-back configuration is performed per Performance.

---

## 10. Ticket Type-specific Aggregation

Ticket-back calculation is aggregated **per ticket type**.

For each ticket type, the system counts the applicable quantity attributed through the reservation's **扱い**, and applies the Production-wide ticket-back rules to that ticket type's quantity using that ticket type's configured price.

Different ticket types are not combined into one sales quantity for ticket-back calculation.

---

## 11. Example

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

and:

```text
販売終了
公演回の [1]日前の [23]時まで

計算方式：累進方式

優先順位 1: 20枚以上 → 40%
優先順位 2: 10枚以上 → 20%
```

These ticket definitions, the sales end setting, and ticket-back rules belong to the Production.

Ticket-back quantities are aggregated separately for each ticket type.

---

## 12. Confirmed Scope

The following are confirmed requirements for this screen:

- Ticket management is configured at the **Production level**.
- Ticket configuration is **common across the Production's Performances**.
- Each ticket entry consists of **チケット種別 + 金額**.
- Multiple ticket entries can be registered.
- A **＋** button adds another ticket type/price row.
- No separate **備考** field is provided.
- Performance-specific discounts or special pricing can be represented in the **チケット種別** text, e.g. **（M/D日分）一般**.
- There is no separately confirmed StageArt discount mechanism.
- **販売終了** is configured as **公演回の［日数］日前の［時］時まで**.
- The calculated sales end date/time is checked when the user presses **予約**.
- **チケットバック is configured at the Production level.**
- The ticket-back **calculation method can be selected between 累進方式 and 分離方式**.
- **Multiple ticket-back conditions can be registered.**
- Ticket-back conditions use **priority ordering**, with priority 1 being highest.
- Each ticket-back condition has a **販売枚数条件 and チケットバック率**.
- Ticket-back is **aggregated separately per ticket type**.
- Each ticket type's configured price is used for its ticket-back calculation.
- A **保存** operation saves the ticket configuration, sales end setting, and Production-level ticket-back settings.
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
- Detailed settlement/payment procedure for ticket back.
- Other accounting rules related to ticket sales.

---

## 13. Implementation Rule

Do not implement ticket type/price configuration as a separate configuration for every Performance.

Do not implement ticket-back conditions or calculation method as separate settings for each Production member.

Do not combine different ticket types into one quantity when calculating ticket-back.

The confirmed business rules are:

```text
Production
├─ Common Ticket Types / Prices
├─ Common 販売終了設定
└─ Common Ticket Back Settings
     ├─ Calculation Method
     │    ├─ 累進方式
     │    └─ 分離方式
     └─ Conditions (multiple, priority ordered)
          ├─ 販売枚数条件
          └─ チケットバック率
```

Sales performance remains attributable to the Production member through the reservation's **扱い**:

```text
Production Member
↓
扱いとして紐づく予約
↓
チケット種別ごとの予約／販売枚数
↓
Production's Ticket Back Settings
↓
チケット種別ごとのチケットバック額
```

The ticket type name is user-defined text and may contain information such as a date-specific discount label. The system must not infer additional business rules from that text unless separately specified.

This screen specification supplements the StageArt Blueprint and is authoritative for the confirmed Ticket Management screen UX described above.
