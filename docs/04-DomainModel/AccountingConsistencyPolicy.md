# StageArt Blueprint

# Domain Consistency Policy : Accounting

Version : 1.0

---

# Purpose

Accounting Domainについて、Organization / Project / ProductionのScope、Journal Entry、Reservation、Check In、仮売上の関係をCanonical仕様として定義する。

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

# Provisional Ticket Sales / 仮売上

**仮売上への振替は会計期間末に行い、Check In時点では行わない。** 会計期間末時点でProduction決算が未確定の場合、当該Productionに関連する売上残高を仮売上へ振り替える。Production決算確定時に仮売上を正式な売上へ振り替える。

Reservation、Check In、入金等の業務Factと、売上・未収金等の会計仕訳は区別する。

基本Flow：

元の売上Journal Entry
    ↓
会計期間末（Production決算未確定）
    ↓
仮売上へ振替
    ↓
Production決算確定
    ↓
正式な売上へ振替

---

# Reservation and Accounts Receivable

Reservation成立によって将来受領する権利が発生する場合、その金額を未収金として管理できる。

Reservation成立時点で、未収金に対応する会計処理を行うことができるが、Ticket Revenueはまだ認識しない。

基本的な考え方：

Reservation成立
    ↓
Accounts Receivable / 未収金

Check In時点で仮売上への振替を行わない。未収金の消込は実際の入金・決済Factに基づいて処理し、仮売上への振替は会計期間末に行う。

---

# Provisional Ticket Sales Journal Entry

会計期間末を仮売上振替の契機とする。CheckInCompletedを仮売上仕訳のBusiness Eventにしない。正式な売上への振替はProduction決算確定時に行う。

Check In時点で仮売上振替仕訳は生成しない。会計期間末に元の売上Journal Entryを仮売上へ振り替える仕訳を生成する。具体的な貸借・相手勘定・Account Typeは関連Account / Journal Entry仕様と整合させる。

実際の入金が同時に発生する場合は、決済方法に応じて現金・預金等への消込を同時に行うことができる。

---

# No Show

No-ShowはCheck Inが完了していないため、Check Inの有無だけを理由に仮売上を記録しない。仮売上への振替対象は会計期間末時点の売上残高と既存の業務・会計ルールに基づいて判断する。

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

仮売上振替後のキャンセル、返金、金額修正等はReversalまたはAdjustment Journal Entryとして記録する。

元のJournal Entryとの関連を追跡可能にする。

---

# Idempotency

同一CheckInCompletedを複数回処理しても、受付Factや関連する会計Factを二重生成してはならない。仮売上振替は会計期間末の対象売上Journal Entryを基に一意に行う。

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
