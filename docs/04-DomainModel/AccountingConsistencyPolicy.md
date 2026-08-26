# StageArt Blueprint

# Domain Consistency Policy : Accounting

Version : 1.0

---

# Purpose

Accounting Domainについて、Organization / Project / ProductionのScope、Journal Entry、Reservation、Check In、Ticket Revenueの関係をCanonical仕様として定義する。

---

# Accounting Source of Truth

会計Factの正本はJournal Entryとする。

Production Actual、Project Actual、Ticket Revenue等をJournal Entryとは別の会計Factとして二重管理しない。

基本構造：

Business Event
    ↓
Accounting Processing
    ↓
Journal Entry
    ↓
Organization Accounting
    ↓
Project / Production Scope集計

---

# Accounting Scope

会計は以下のScopeから参照できる。

Organization
    = 団体全体の財務状況

Project
    = 企画全体の予実管理

Production
    = 個別公演の決算・収支確認

同一のJournal Entryを各Scopeから集計する。

Organization / Project / Productionごとに同じ取引を別仕訳として計上してはならない。

---

# Journal Entry Ownership

Journal Entryは必ず一つのOrganizationに所属する。

Productionに関連するJournal Entryは、そのProductionが所属するProjectおよびOrganizationのScopeから集計可能であることを基本とする。

Productionに関連する会計FactをProduction専用帳簿へ二重計上しない。

---

# Project Scope

Project Actualは、Projectに所属するProduction等に関連するJournal EntryをProject Scopeで集計する。

Project BudgetとProject Actualを比較することでProject全体の予実を確認する。

Project ActualをJournal Entryとは別の正本データとして保持しない。

---

# Production Scope

Production Actualは、Productionに関連するJournal Entryから集計する。

Production BudgetとProduction Actualを比較して、個別公演の収支および決算を確認する。

Production Settlementは表示・業務概念であり、独立した会計帳簿ではない。

---

# Ticket Revenue Recognition

Ticket Revenueは**Check In時点で認識する**。

Reservation成立時点ではTicket Revenueを認識しない。

基本Flow：

Reservation成立
    ↓
未収金
    ↓
来場
    ↓
Check In
    ↓
CheckInCompleted
    ↓
Ticket Revenue Recognition
    ↓
Journal Entry

---

# Reservation and Accounts Receivable

Reservation成立によって将来受領する権利が発生する場合、その金額を未収金として管理できる。

Reservation成立時点で、未収金に対応する会計処理を行うことができるが、Ticket Revenueはまだ認識しない。

基本的な考え方：

Reservation成立
    ↓
Accounts Receivable / 未収金

Check In時点で、未収金をTicket Revenueへ振り替える。

---

# Ticket Revenue Journal Entry

CheckInCompletedをRevenue RecognitionのBusiness Eventとする。

例えば3,000円の予約について来場した場合：

Debit
    未収金 3,000円

Credit
    チケット売上 3,000円

実際の入金が同時に発生する場合は、決済方法に応じて現金・預金等への消込を同時に行うことができる。

---

# No Show

No-ShowはCheck Inが完了していないため、原則としてTicket Revenueを認識しない。

未収金が存在する場合、その後のキャンセル・免除・その他の調整によって処理する。

No-Showを理由に過去のJournal Entryを直接削除・変更してはならない。

---

# Price Snapshot

Ticket Revenueの金額はReservation成立時点で保存されたPrice Snapshotを基準とする。

Ticket Masterの現在価格を参照して過去Reservationの金額を再計算してはならない。

GuestCountが複数の場合も、予約時点で確定した取引金額を基礎とする。

---

# Payment Timing

Revenue Recognitionと現金・預金の入出金は別概念として扱う。

来場時に支払われる場合は、Revenue Recognitionと入金を同一取引として処理できる。

事前入金がある場合は、Revenue Recognition前の受領として別途管理する必要がある。

決済手段による具体的なAccountの選択はAccounting Account / Payment Domainで定義する。

---

# Reversal and Adjustment

POSTED Journal Entryは直接変更・削除しない。

Revenue Recognition後のキャンセル、返金、金額修正等はReversalまたはAdjustment Journal Entryとして記録する。

元のJournal Entryとの関連を追跡可能にする。

---

# Idempotency

同一CheckInCompletedを複数回処理しても、同一Ticket Revenueを二重計上してはならない。

Accounting ProcessingはSource Event ID等を利用してIdempotentに処理する。

---

# Budget / Actual

Budgetは計画値、Journal Entryは実績の会計Factとする。

ActualはJournal Entryから集計する。

VarianceはBudgetとActualの差から算出し、独立した会計Factとして二重保存しない。

---

# Accounting Activation

AccountingはOrganization単位のオプション機能とする。

Accounting未開始のOrganizationに対して、Account Master、Budget、Journal Entry等の会計初期設定を必須にしてはならない。

Accountingを有効化する場合は、現在の資金を入力してOpening Balanceを登録する。

最低限、現金と預金を分けて入力できるものとする。

一度Accountingを有効化したOrganizationは、会計履歴保護のため単純にOFFへ戻せない。

---

# Accounting Period

Journal EntryにはJournal Dateを持たせ、該当するAccounting Periodへ計上する。

Productionが複数会計年度にまたがる場合でも、Production全体を一つの年度へまとめて計上しない。

各Journal EntryをJournal Dateに従って該当Periodへ計上する。

締められたAccounting PeriodのPOSTED Journal Entryを直接変更してはならない。

---

# Settlement and Closing

Productionの決算完了は、Productionに必要な未収金・未払金・返金・調整等の処理が完了した状態を意味する。

Productionの決算完了とOrganizationのAccounting Period Closeは別概念である。

ProductionがCOMPLETEDになった後に計上漏れ等が判明した場合も、過去仕訳を書き換えずAdjustment Journal Entryを追加する。

---

# Canonical Ticket Accounting Flow

```text
Reservation
    │
    ├─ Price Snapshot
    │
    └─ 未収金
          │
          ▼
       Check In
          │
          ▼
   CheckInCompleted
          │
          ▼
 Ticket Revenue Recognition
          │
          ▼
    Journal Entry
          │
          ├── Organization Scope
          ├── Project Scope
          └── Production Scope
```

このFlowをTicket Revenueに関するCanonical Ruleとする。
