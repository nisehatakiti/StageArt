# StageArt Blueprint
# Chapter 25 : Production Ticket Sales Rules — Quota and Ticket Back

Version : 1.3
Status : Confirmed business specification

---

## 1. Purpose

This document records the confirmed relationship between Production information, Production member management, ticket sales, ticket-back calculation, and settlement concerning **ノルマ** and **チケットバック**.

The **チケットバックの設定はProduction（公演）全体に対して行う**。

Ticket reservation results are used for the individual member's current sales/preview information, while the final チケットバック amount is calculated from actual attendance.

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

## 4. Sales Count and Attendance Count

チケットバックには、**見込み計算**と**最終確定計算**の2つの段階がある。

### 4.1 見込み計算

個人ページに表示するチケットバック金額見込みは、**実販売額ではなくチケットの予約枚数をベース**に計算する。

予約の **扱い** によりProduction member単位で予約枚数を集計し、Productionに設定されたチケットバック条件・計算方式を適用して、チケットバック金額見込みを算出する。

この見込み額は、実際の来場者数が確定する前の参考情報である。

### 4.2 最終確定計算

チケットバックの最終的な金額は、**実際に来場した客数（実来場枚数）をベース**に計算する。

公演終了後、対象となる実来場枚数を集計し、Productionに設定されたチケットバック条件・計算方式を適用して、各個人のチケットバック金額を確定する。

予約枚数と実来場枚数は同じものとして扱わない。

---

## 5. Relationship with Reservation Handling

A ticket reservation may have a Production member recorded as its **扱い**.

The ticket quantity of reservations associated with a member as **扱い** is used as that member's reservation-based ticket count for preview purposes.

This reservation-based count is used to display the member's current ticket-back estimate on the individual page.

A reservation without a specified **扱い** is not attributed to an individual member for this purpose.

---

## 6. Unpaid Amount and Settlement

The final チケットバック amount for each individual is held as an **未払い金**.

This unpaid amount is managed independently of whether the Organization's accounting function is enabled.

### Accounting enabled

When accounting is enabled, the confirmed チケットバック amount is also managed as an accounting-related unpaid amount.

### Accounting disabled

When accounting is not enabled, the confirmed チケットバック amount is still retained by StageArt as an unpaid amount for the individual. Accounting functionality is not required merely to retain the チケットバック unpaid amount.

### Settlement

When settlement is performed from Production management, the applicable individual's チケットバック未払い金 is settled and becomes **0円**.

The exact settlement operation UI, settlement history, payment method, and other accounting details are not defined by this chapter unless separately confirmed.

---

## 7. Business Relationship

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
       予約枚数
            ↓
     個人ページ：チケットバック金額見込み

公演終了
     ↓
    実来場枚数
     ↓
Productionのチケットバック条件を適用
     ↓
チケットバック確定額
     ↓
個人別未払い金
     ↓
公演管理で精算
     ↓
未払い金 = 0円
```

The purpose of **扱い** is therefore to provide the link required to count ticket reservations by Production member and, ultimately, to calculate that member's ticket-back amount.

チケットバックの条件・計算方式はメンバーごとに個別設定するものではなく、Production全体の設定とする。

---

## 8. Scope Not Yet Defined

This specification does not define:

- The exact value/type of ノルマ.
- Whether ノルマ is expressed only as a ticket quantity or through another representation.
- The exact settlement/payment procedure beyond the confirmed unpaid-amount state and zeroing on settlement.
- Whether ticket-back calculation differs by ticket type.
- Treatment of cancelled reservations in the final sales count.
- Exact rules for defining/counting an actual attendance when a reservation contains multiple tickets.
- Other accounting rules related to ticket sales.
- Exact calculation/display formula for the individual-page **ノルマ progress** beyond the confirmed display concept of **予約枚数 / ノルマ枚数**.

These items must be confirmed separately before implementation.
