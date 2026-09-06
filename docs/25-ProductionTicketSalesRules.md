# StageArt Blueprint
# Chapter 25 : Production Ticket Sales Rules — Quota and Ticket Back

Version : 1.4
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
- **精算**

チケットバックはProduction単位で設定する。

チケットバックには以下を設定できる。

- **計算方式**
  - **累進方式**
  - **分離方式**
- **チケット販売枚数に応じた複数の条件**
  - 条件となる販売枚数
  - その条件に対応するチケットバック率
  - **優先順位**

条件は優先順位の高いものから処理する。優先順位1が最も高い。

例えば、以下の条件を設定する場合：

| 優先順位 | 販売枚数条件 | チケットバック率 |
|---:|---:|---:|
| 1 | 20枚以上 | 40% |
| 2 | 10枚以上 | 20% |

累進方式では25枚の場合、優先順位1の20枚以上条件が適用され、25枚全体に40%を適用する。

---

## 3. Ticket Back Calculation Methods

### 3.1 累進方式

**チケット売り上げ枚数全体に対して、到達した条件のチケットバック率を適用する方式。**

複数の条件を満たす場合は、**優先順位が最も高い達成条件**の率を適用する。

例えば、以下の条件の場合：

- 優先順位1：20枚以上 → 40%
- 優先順位2：10枚以上 → 20%

25枚売った場合は、優先順位1の20枚以上の条件を達成しているため、25枚全体に40%を適用する。

```text
チケット代 × 25枚 × 40%
```

15枚の場合は優先順位1を満たさないため、優先順位2の10枚以上 → 20%を適用する。

9枚の場合は条件未達のため、チケットバックは発生しない。

### 3.2 分離方式

**チケット売り上げ枚数を条件の段階ごとに分離し、それぞれの段階に対応するチケットバック率を適用する方式。**

例えば、以下の条件の場合：

- 優先順位1：20枚以上 → 40%
- 優先順位2：10枚以上 → 20%

25枚売った場合は、10枚分を20%、残り15枚分を40%として計算する。

```text
チケット代 ×（10枚 × 20% ＋ 15枚 × 40%）
```

条件は優先順位の高いものから処理し、各段階の対象枚数を確定する。

したがって、累進方式と分離方式では、同じ販売枚数・同じ条件設定であってもチケットバック額が異なる。

### 3.3 条件未達の場合

設定した最初の条件に達していない場合、その条件によるチケットバックは発生しない。

例えば「10枚以上 → 20%」のみが設定されている場合、9枚以下ではチケットバックは発生しない。

---

## 4. Ticket Type Aggregation

チケットバックの枚数集計および金額計算は、**チケット種別ごとに行う**。

Productionで設定された各チケット種別の設定金額を使用して、種別ごとの枚数に対してチケットバックを計算する。

例えば、以下のチケット設定の場合：

```text
一般：3,000円
学生：2,000円
```

一般チケットと学生チケットを別々に集計し、それぞれのチケット種別の金額を基準としてチケットバックを算出する。

チケットバック計算に使用する金額は、**Productionで設定されたチケット種別の金額**とする。

現時点では、StageArtにチケット販売価格を予約時に割引するための独立した割引機能は設けない。

---

## 5. Sales Count and Attendance Count

チケットバックには、**見込み計算**と**最終確定計算**の2つの段階がある。

### 5.1 見込み計算

Homeに表示するチケットバック金額見込みは、**実販売額ではなくチケットの予約枚数をベース**に計算する。

予約の **扱い** によりProduction member単位で予約枚数を集計し、Productionに設定されたチケットバック条件・計算方式を適用して、チケットバック金額見込みを算出する。

チケット種別ごとに予約枚数を集計し、それぞれのチケット種別の設定金額を使用して計算する。

この見込み額は、実際の来場者数が確定する前の参考情報である。

### 5.2 予約変更・キャンセル

チケット変更によって予約枚数が減少した場合、変更後の予約枚数を使用する。

変更前の予約枚数は見込み計算の対象から除外され、現在の予約内容が基準となる。

### 5.3 最終確定計算

チケットバックの最終的な金額は、**受付でチェックインした実来場枚数のみをベース**に計算する。

公演終了後、対象となる実来場枚数をチケット種別ごとに集計し、Productionに設定されたチケットバック条件・計算方式を適用して、各個人のチケットバック金額を確定する。

招待・関係者等、**予約の「扱い」として個人に紐付けないチケットは、個人のチケットバック集計対象外**とする。

予約枚数と実来場枚数は同じものとして扱わない。

---

## 6. Relationship with Reservation Handling

A ticket reservation may have a Production member recorded as its **扱い**.

The ticket quantity of reservations associated with a member as **扱い** is used as that member's reservation-based ticket count for preview purposes.

This reservation-based count is used to display the member's current ticket-back estimate on Home.

A reservation without a specified **扱い** is not attributed to an individual member for this purpose.

For final ticket-back calculation, only the tickets actually checked in at Reception / Check-in are counted for the individual to whom the reservation's **扱い** is assigned.

---

## 7. Quota

ノルマの未達に対する具体的な措置はStageArtでは定めない。

各団体が、ノルマ未達分について以下を含む任意の運用を行える。

- 未達のままとする
- 未達分を本人の負担・買取等として扱う
- その他、団体独自のルールを設定する

StageArtはノルマの達成状況を把握・表示できるようにするが、未達に対する罰則や買取を強制しない。

ノルマ進捗はHomeで、予約枚数とノルマ枚数を用いて確認できる。

---

## 8. Unpaid Amount and Settlement

The final チケットバック amount for each individual is held as an **未払い金**.

This unpaid amount is managed independently of whether the Organization's accounting function is enabled.

### Accounting enabled

When accounting is enabled, the confirmed チケットバック amount is also managed as an accounting-related unpaid amount.

### Accounting disabled

When accounting is not enabled, the confirmed チケットバック amount is still retained by StageArt as an unpaid amount for the individual. Accounting functionality is not required merely to retain the チケットバック unpaid amount.

### Settlement

Production management provides a **精算** menu.

The settlement screen displays a list of Production members and their applicable チケットバック未払い金.

The manager can settle each individual member separately using a **精算済み** operation.

When settlement is performed for an individual, that individual's チケットバック未払い金 becomes **0円** and the individual is treated as settled for the Production.

Settlement is performed per individual; settling one member does not settle other members.

Detailed payment method, accounting journal behavior, and settlement history are implementation/business details outside the scope of this chapter unless separately confirmed.

---

## 9. Business Relationship

The confirmed relationship is:

```text
Production
├─ ノルマ
├─ チケットバック
│    ├─ 計算方式（累進方式／分離方式）
│    └─ 条件（複数）
│         ├─ 優先順位
│         ├─ 販売枚数条件
│         └─ チケットバック率
│
├─ 精算
│
└─ Production Member
     └─ 扱いとして紐づく予約
            ↓
       予約枚数
       （チケット種別ごと）
            ↓
     Home：チケットバック金額見込み

公演終了
     ↓
受付でチェックインした実来場枚数のみ
     ↓
チケット種別ごとに集計
     ↓
Productionのチケットバック条件を適用
     ↓
チケットバック確定額
     ↓
個人別未払い金
     ↓
公演管理「精算」
     ↓
個人ごとに精算済み
     ↓
未払い金 = 0円
```

The purpose of **扱い** is therefore to provide the link required to count ticket reservations by Production member and, ultimately, to calculate that member's ticket-back amount.

チケットバックの条件・計算方式はメンバーごとに個別設定するものではなく、Production全体の設定とする。

---

## 10. Scope Not Yet Defined

The following items remain outside this chapter:

- Exact settlement/payment method.
- Accounting journal details associated with settlement.
- Settlement history display/details.
- Exact validation/input constraints for ticket-back conditions.
- Maximum number of ticket-back conditions.
- Other accounting rules related to ticket sales.

The following items are confirmed by this version and must not be treated as undefined:

- Ticket-back calculation is performed separately by ticket type.
- Production ticket-type configured price is used as the calculation amount.
- Reservation quantity is used for Home estimates.
- When a ticket change reduces/cancels a reservation, the current reservation quantity is used.
- Final calculation uses only tickets checked in at Reception / Check-in.
- Invitation/related-person tickets are outside individual ticket-back attribution because they are not assigned as **扱い**.
- No StageArt discount mechanism is assumed for this calculation.
- Quota shortfall penalties or buyout rules are left to each Organization.
- Production provides a per-member **精算** operation.
