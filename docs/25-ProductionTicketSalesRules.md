# StageArt Blueprint
# Chapter 25 : Production Ticket Sales Rules — Quota and Ticket Back

Version : 1.2
Status : Confirmed business specification

---

## 1. Purpose

This document records the confirmed relationship between Production information, Production member management, ticket sales management, actual attendance, and settlement concerning **ノルマ** and **チケットバック**.

The **チケットバックの設定はProduction（公演）全体に対して行う**.

Ticket sales are counted by Production member according to the reservation's **扱い**, while the Production-wide ticket-back conditions and calculation method are applied to that member's sales count.

The final チケットバック amount is calculated based on **actual attendance**, not merely on the number of reservations.

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

チケットバックの条件・計算方式はメンバーごとに個別設定するものではなく、Production全体の設定とする。

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

## 4. Relationship with Reservation Handling and Actual Attendance

A ticket reservation may have a Production member recorded as its **扱い**.

The **扱い** is used to identify the Production member associated with the reservation and to aggregate that member's ticket sales count.

However, the final チケットバック calculation is based on the **actual number of customers who attended**, rather than simply the number of reservations made.

The confirmed conceptual flow is:

```text
予約
 ↓
扱い
 ↓
実際の来場
 ↓
実来場枚数を集計
 ↓
Productionのチケットバック条件・計算方式を適用
 ↓
各個人のチケットバック確定額
```

A reservation without a specified **扱い** is not attributed to an individual member for this purpose.

The exact treatment of individual ticket types and other exceptional cases remains outside this specification unless separately confirmed.

---

## 5. Unpaid Amount Management

Once the final チケットバック amount is calculated from actual attendance, the amount owed to each individual is retained as an **未払い金**.

The management of this unpaid amount does **not require the Accounting function to be enabled**.

### 5.1 Accounting ON

When the Production/Organization uses the StageArt Accounting function, the confirmed チケットバック amount is also managed as an accounting-related unpaid amount according to the Accounting rules.

### 5.2 Accounting OFF

Even when the Accounting function is not enabled, StageArt must retain the チケットバック amount as an unpaid amount outside the Accounting function.

Therefore:

```text
チケットバック確定額
        ↓
      未払い金
        ↓
 ┌──────┴──────┐
 ↓             ↓
会計ON         会計OFF
 ↓             ↓
会計上でも管理  StageArt内部で管理
```

The purpose is to ensure that a person is not considered paid merely because the Accounting function is disabled.

---

## 6. Settlement

チケットバックの精算は、Production管理側から行う。

When the Production manager performs settlement, the relevant individual's outstanding チケットバック amount is settled and the retained **未払い金** becomes **0円**.

Conceptually:

```text
実来場数
 ↓
チケットバック確定
 ↓
未払い金として保持
 ↓
公演管理で精算
 ↓
未払い金 = 0円
```

The exact settlement UI, settlement date/history representation, and payment method are not defined by this specification unless separately confirmed.

---

## 7. Individual Page Visibility

The individual user's StageArt page must allow the user to see information concerning their own Production ticket sales management.

At minimum, the individual page displays:

- **ノルマ**
- **チケットバック金額見込み**

The チケットバック金額見込み is a forecast/expected amount and is distinct from the final チケットバック amount determined from actual attendance.

The exact calculation timing and display details for the チケットバック金額見込み are not fixed by this specification unless separately confirmed.

After the final チケットバック amount is determined, the corresponding unpaid amount is retained as described in Section 5.

---

## 8. Business Relationship

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
        実際の来場
            ↓
        実来場枚数
            ↓
      Productionのチケットバック条件を適用
            ↓
        チケットバック確定額
            ↓
          未払い金
            ↓
        公演管理で精算
            ↓
        未払い金 = 0円
```

The purpose of **扱い** is therefore to provide the link required to count ticket sales by Production member and apply the Production's ノルマ and チケットバック rules.

The チケットバック conditions and calculation method are Production-wide settings and are not configured individually for each member.

---

## 9. Scope Not Yet Defined

This specification does not define:

- The exact value/type of ノルマ.
- Whether ノルマ is expressed only as a ticket quantity or through another representation.
- The exact calculation formula for the チケットバック金額見込み.
- The exact settlement/payment method.
- Settlement history/details beyond the fact that the outstanding amount becomes 0円 when settled.
- Whether ticket-back calculation differs by ticket type.
- Treatment of cancelled reservations in the final sales count.
- Other accounting rules related to ticket sales.
- Other exceptional cases affecting actual attendance counting.

These items must be confirmed separately before implementation.
