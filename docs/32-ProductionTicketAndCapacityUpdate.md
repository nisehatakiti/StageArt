# StageArt Blueprint
# Chapter 32 : Production Ticket Management and Capacity Update

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This chapter records the latest confirmed Production management specifications for:

- チケット管理
  - チケット設定
  - チケットバック／ノルマ設定
- 公演情報・公演回における定員

This chapter supersedes conflicting older menu and screen assumptions in earlier Blueprint chapters.

---

# 2. Production Management Menu

The confirmed Production menu is:

```text
Production
│
├─ 公演情報
├─ メンバー管理
├─ 公演回管理
├─ チケット管理
│   ├─ チケット設定
│   └─ チケットバック／ノルマ設定
├─ 稽古管理
└─ 小屋入り～本番
```

There are no separate top-level menus for ノルマ管理, チケットバック管理, or 定員管理.

---

# 3. チケット設定

## 3.1 Purpose

チケット設定 manages:

- チケット名
- 料金
- 備考
- チケット情報公開日時
- Production共通の販売開始日時
- Production共通の販売終了ルール

It is responsible for price and sales rules, not capacity management.

## 3.2 Screen Structure

```text
[ Production名 ]

チケット設定

────────────────────────────

■ 登録済みチケット

削除　チケット名　料金　備考
────────────────────────────
□　　[ 一般 ]　 [ 5000 ] 円　[ ]
□　　[ 学生 ]　 [ 3000 ] 円　[ ]

────────────────────────────

■ チケットを追加

チケット名
[ ]

料金
[ ] 円

備考
[ ]

[ ＋ 追加 ]

────────────────────────────

■ 公開設定

情報公開日時
[ YYYY/MM/DD HH:MM ]

────────────────────────────

■ 販売設定

販売開始日時
[ YYYY/MM/DD HH:MM ]

販売終了

○ 公演前日の [ HH:MM ] まで
○ 開演の [   ] 時間前まで

────────────────────────────

                         [ 更新 ]
```

Existing rows are editable directly. Added rows are staged in the screen and persisted by ［更新］.

## 3.3 Ticket Fields

Each ticket has:

- チケット名：必須
- 料金：必須、正の整数、0円不可、負数不可
- 備考：任意

Free tickets and invitation tickets are not handled by this ticket-price management function.

「当日券」等は特別なシステム区分ではなく、通常のチケット名で表現する。

## 3.4 Publication

Ticket information has one Production-wide 情報公開日時.

Before publication:
- ticket information is not shown on the public page
- no purchase/reservation entry is shown

After publication but before sales start:
- ticket information is shown
- reservation/purchase is not available

## 3.5 Sales Start

販売開始日時 is one Production-wide absolute datetime.

Before this datetime, reservations/purchases are unavailable.

## 3.6 Sales End

販売終了 is not entered as a separate absolute datetime per Performance.

One Production-wide rule is selected:

1. 公演前日の指定時刻まで
2. 開演の指定時間前まで

The system calculates the actual sales end datetime separately for each Performance.

## 3.7 Capacity Responsibility

Capacity is not managed in チケット設定.

Production capacity is maintained in 公演情報 and inherited by 公演回.

---

# 4. チケットバック／ノルマ設定

## 4.1 Screen Structure

```text
チケットバック／ノルマ設定

────────────────────────────

■ チケットバック

[ 設定する ／ 設定しない ]

設定する場合のみ：

計算方式
○ 累進方式
○ 分離方式

優先順位　枚数　条件　料率
────────────────
[ 1 ]　[ ]　[ 以上／以下／未満 ]　[ ] %

[ ＋ 条件を追加 ]

※条件は優先順位の数字が小さいものから順に判定され、
　最初に一致した条件を適用します。

────────────────────────────

■ チケットノルマ

[ 設定する ／ 設定しない ]

設定する場合のみ：

ノルマ枚数
[ ] 枚

ノルマ未達時の扱い
○ 買取にする
○ 買取にしない

買取にする場合のみ：

未達チケット単価
[ ] 円

────────────────────────────

                         [ 更新 ]
```

The setting switch controls whether the corresponding detail section is displayed.

---

## 4.2 Ticket-back Condition Processing

Each condition has:

- 優先順位
- 枚数
- 条件：以上／以下／未満
- 料率

Conditions are evaluated in ascending priority number order.

**The first matching condition is applied.**

Priority therefore has business meaning and is not merely a display order.

---

## 4.3 累進方式

The final sales quantity is evaluated against the conditions.

The rate from the first matching condition is applied to the **entire sales quantity and its corresponding ticket sales amount**.

Example:

```text
21枚以上 → 20%
11枚以上 → 15%
1枚以上  → 10%
```

For 15 sold tickets:

```text
15枚 × チケット価格 × 15%
```

The resulting amount is the member's **未収金**. The Production manager owes that amount to the member.

---

## 4.4 分離方式

Starting with the first ticket and proceeding one by one, the applicable condition is determined for each ticket count.

The applicable rate is multiplied by that ticket's price, and all ticket-level results are accumulated.

Example with the same stages and 15 sold tickets:

- tickets 1–10 use the rate applicable to those counts
- tickets 11–15 use the rate applicable to those counts
- all results are accumulated

The final result is the member's **未収金**, and the Production manager owes that amount to the member.

---

## 4.5 ノルマ

ノルマ is Production-wide configuration:

- 設定する／設定しない
- ノルマ枚数
- ノルマ未達時の扱い

The quota is not configured through a separate ノルマ管理 menu.

When 買取にする is selected, an additional field is shown:

- 未達チケット単価

If a member fails to meet the quota:

```text
未払い金 ＝ 未達枚数 × 未達チケット単価
```

This amount is the member's **未払い金**. The member owes the amount to the Production manager.

---

## 4.6 Settlement Boundary

Ticket-back and quota shortfall calculations create separate receivable/payable obligations.

Automatic offsetting between:

- member 未収金（ticket back）
- member 未払い金（quota buyout）

is **not part of ticket management**.

Offsetting and final net settlement are handled later by the Production settlement function.

---

# 5. Relationship to Existing Blueprint

The following earlier assumptions are superseded where they conflict with this chapter:

- separate ノルマ管理 menu
- separate チケットバック管理 menu
- per-member quota configuration
- ticket type-specific sales periods
- ticket management responsibility for capacity
- capacity rule stating that Production capacity changes do not affect existing Performances

For capacity, Chapter 23 Version 1.1 is authoritative.

For the latest combined ticket screen and ticket-back/quota rules, this chapter is authoritative.

---

# 6. Status

All rules in this chapter are confirmed unless explicitly marked as future settlement responsibility.
