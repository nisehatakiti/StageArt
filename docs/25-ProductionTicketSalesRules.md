# StageArt Blueprint
# Chapter 25 : Production Ticket Sales Rules — Quota and Ticket Back

Version : 1.1
Status : Confirmed business specification

---

## 1. Purpose

This document records the confirmed relationship between Production information, Production member management, and ticket sales management concerning **ノルマ** and **チケットバック**.

The **チケットバックの設定はProduction（公演）全体に対して行う**。

チケット販売実績は、予約の **扱い** によりProduction member単位で集計し、その販売枚数に対してProductionに設定されたチケットバック条件・計算方式を適用する。

---

## 2. Production-level Management

The Production information/management area must include the concepts of:

- **ノルマ**
- **チケットバック**

チケットバックはProduction単位で設定する。

チケットバックには以下を設定できる。

- **計算方式**
  - **累進方式**
  - **分離方式**
- **チケット販売枚数に応じた複数の条件**
  - 条件となる販売枚数
  - その条件に対応するチケットバック率

例えば、以下のような複数条件を設定できる。

| 販売枚数条件 | チケットバック率 |
|---:|---:|
| 10枚以上 | 20% |
| 20枚以上 | 40% |

条件は複数設定でき、販売枚数に応じて適用する。

---

## 3. Ticket Back Calculation Methods

### 3.1 累進方式

**チケット売り上げ枚数全体に対して、到達した条件のチケットバック率を適用する方式。**

例えば、以下の条件の場合：

- 10枚以上 → 20%
- 20枚以上 → 40%

25枚売った場合は、20枚以上の条件を達成しているため、25枚全体に40%を適用する。

```text
チケット代 × 25枚 × 40%
```

つまり、条件を達成した時点で、それまでに販売した枚数を含む売上枚数全体が、到達した条件のバック率の対象となる。

### 3.2 分離方式

**チケット売り上げ枚数を条件の段階ごとに分離し、それぞれの段階に対応するチケットバック率を適用する方式。**

例えば、以下の条件の場合：

- 10枚以上 → 20%
- 20枚以上 → 40%

25枚売った場合は、10枚分を20%、20枚以上となる部分を40%として計算する。

```text
チケット代 ×（10枚 × 20% ＋ 15枚 × 40%）
```

したがって、累進方式と分離方式では、同じ販売枚数・同じ条件設定であってもチケットバック額が異なる。

### 3.3 条件未達の場合

設定した最初の条件に達していない場合、その条件によるチケットバックは発生しない。

例えば「10枚以上 → 20%」のみが設定されている場合、9枚以下ではチケットバックは発生しない。

---

## 4. Relationship with Reservation Handling

A ticket reservation may have a Production member recorded as its **扱い**.

The ticket quantity of reservations associated with a member as **扱い** is used as that member's ticket sales count.

このProduction member単位のチケット販売枚数に対して、Productionに設定されたチケットバックの条件・計算方式を適用する。

A reservation without a specified **扱い** is not attributed to an individual member for this purpose.

---

## 5. Business Relationship

The confirmed relationship is:

```text
Production
├─ ノルマ
├─ チケットバック
│    ├─ 計算方式（累進方式／分離方式）
│    └─ 条件（複数）
│         ├─ 販売枚数条件
│         └─ チケットバック率
│
└─ Production Member
     └─ 扱いとして紐づく予約
            ↓
        チケット販売枚数
            ↓
      Productionのチケットバック条件を適用
            ↓
        チケットバック額
```

The purpose of **扱い** is therefore not merely to identify the person who introduced a reservation. It provides the link required to count ticket sales by Production member and apply the Production's ノルマ and チケットバック rules.

チケットバックの条件・計算方式はメンバーごとに個別設定するものではなく、Production全体の設定とする。

---

## 6. Scope Not Yet Defined

This specification does not define:

- The exact value/type of ノルマ.
- Whether ノルマ is expressed only as a ticket quantity or through another representation.
- The exact settlement/payment procedure for チケットバック.
- Whether ticket-back calculation differs by ticket type.
- Treatment of cancelled reservations in the final sales count.
- Other accounting rules related to ticket sales.

These items must be confirmed separately before implementation.
