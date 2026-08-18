# StageArt Blueprint

# Domain Consistency Policy : Ticket Revenue / Accounting

Version : 1.0

---

# Purpose

Ticket RevenueとAccounting Domainの責務境界を整理し、CheckIn、Reservation、Journal Entry、およびOrganization / Project / Production Accountingとの関係を定義する。

本書は、既存のAccounting PolicyおよびCheck In Domainを横断して確認した結果を記録する。

---

# Canonical Accounting Source

会計Factの正本はJournal Entryとする。

Ticket Revenue、Production Actual、Project Actual等をJournal Entryとは別の会計Factとして二重管理しない。

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

# Domain Responsibility

Reservation Domain：

- 予約Fact
- Guest Count
- Price Snapshot
- Reservation Status

Check In Domain：

- 来場受付Fact
- Check In Status
- Check In Operator
- CheckInCompleted Event

Ticket Domain：

- Ticket券種
- 料金区分
- 販売区分
- Ticket Price

Accounting Domain：

- Revenue Recognition
- Accounts Receivable / 未収金
- Accounts Payable / 未払金
- Journal Entry
- Journal Entry Line
- Settlement
- Reversal / Adjustment
- Accounting Period

Ticket Revenueそのものを独立した会計帳簿として保持しない。

---

# Ticket Amount Source

会計処理に利用するTicket取引金額は、Reservation成立時に保存されたPrice Snapshotを基準とする。

Ticketの現在価格を参照して過去Reservationの金額を再計算してはならない。

Guest Countが複数の場合も、予約時点で確定した取引金額を基礎とする。

---

# Scope Aggregation

Ticket Revenueに関連するJournal Entryは、所属OrganizationのAccountingへ含まれる。

Journal EntryにProject / ProductionのScopeを特定できる情報を保持し、各Scopeから集計できるようにする。

基本構造：

Organization
    ↓
Journal Entry
    ├── Project Scope
    └── Production Scope

これにより、同一のTicket取引をOrganization / Project / Productionへ別々に計上しない。

---

# Project / Production Actual

Ticket Revenueを含む実績はJournal Entryから集計する。

Production ActualはProductionに関連するJournal Entryから算出する。

Project ActualはProjectに所属するProduction等に関連するJournal EntryをProject Scopeで集計する。

Budgetは計画値であり、ActualはJournal Entryから算出される実績値である。

---

# Idempotency

同一Business Eventを複数回処理しても、同一会計Factを二重計上してはならない。

Accounting Processingは、Eventの一意識別情報等を利用してIdempotentに実行できる構造とする。

---

# Reversal

Ticket取引やCheck Inに関する取消・修正が発生した場合、既存Journal Entryを物理削除・直接書換えするのではなく、Accounting DomainのReversalまたはAdjustmentとして記録する。

元の会計Factとの関連を追跡できることを基本とする。

---

# Important Consistency Issue

既存Blueprint間には、Ticket Revenueを認識するタイミングについて二つの記述が存在する。

1. Check In Domainでは、CheckInCompletedを契機としてTicket Revenueを会計へ認識すると定義している。
2. AccountingPolicyでは、Ticket予約等によって受取権利が確定した時点で未収金およびTicket Revenueを計上すると定義している。

この二つは同一取引のRevenue Recognition Timingとしては両立しない可能性がある。

したがって、本件はImplementation上の推測で解消せず、**Revenue Recognition Timingを別途最終決定する必要がある**。

---

# Decision Candidates

候補A：Reservation成立時にRevenue Recognition

Reservation成立
    ↓
未収金 / Ticket Revenue
    ↓
入金・Check In等
    ↓
未収金消込

候補B：Check In成立時にRevenue Recognition

Reservation成立
    ↓
予約Factのみ
    ↓
Check In
    ↓
未収金または現金 / Ticket Revenue

どちらをCanonical Ruleとするかを決定後、CheckIn.mdおよびAccountingPolicy.mdを統一する。

---

# Current Rule

Revenue Recognition Timingについては、本Consistency Policy作成時点では未確定として扱う。

それ以外の責務分離、Journal Entry正本、Price Snapshot、Scope集計、Idempotency、Reversalのルールは確定とする。
