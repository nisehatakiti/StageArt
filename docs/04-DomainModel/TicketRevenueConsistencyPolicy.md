# StageArt Blueprint

# Domain Consistency Policy : Ticket Revenue / Accounting

Version : 1.1

---

# Purpose

Ticket RevenueとAccounting Domainの責務境界を整理し、Reservation、Check In、Journal Entry、およびOrganization / Project / Production Accountingとの関係を定義する。

本書は、Accounting PolicyおよびCheck In Domainを横断したTicket RevenueのCanonical Ruleを定義する。

---

# Canonical Revenue Recognition Rule

**Check In時点では仮売上へ振り替えない。会計期間末に、決算未確定Productionの売上残高を仮売上へ振り替え、Production決算確定時に正式な売上へ振り替える。**

Reservation成立時点では、Ticket Revenueを売上として認識しない。Check Inは来場Factであり、仮売上への振替契機ではない。

基本Flow：

Reservation / Ticket取引Fact
    ↓
取引・入金等に応じた既存Journal Entry
    ↓
会計期間末（Production決算未確定）
    ↓
売上残高を仮売上へ振替
    ↓
Production決算確定
    ↓
仮売上を正式な売上へ振替

したがって、予約済みで未来場のTicketは、Revenueとして認識せず、未収金として扱う。

---

# Domain Responsibility

Reservation Domain：

- 予約Fact
- Guest Count
- Price Snapshot
- Reservation Status
- 未収金の対象となる予約金額の確定情報

Check In Domain：

- 来場受付Fact
- Check In Status
- Check In Operator
- CheckInCompleted Event
- 会計期間末の仮売上振替対象

Ticket Domain：

- Ticket券種
- 料金区分
- 販売区分
- Ticket Price

Accounting Domain：

- 仮売上の記録と正式売上への振替
- Accounts Receivable / 未収金
- Accounts Payable / 未払金
- Journal Entry
- Journal Entry Line
- Settlement
- Reversal / Adjustment
- Accounting Period

Ticket Revenueそのものを独立した会計帳簿として保持しない。Check Inは来場受付Factであり、仮売上振替は会計期間末に行う。

---

# Reservation and Accounts Receivable

Reservation成立時点では、Price Snapshotに基づく予約金額を未収金の対象として扱う。

例えば3,000円のTicket予約が成立した場合：

未収金 3,000 / 予約に対応する未収金認識

ただし、この時点ではTicket Revenueを認識しない。

実際の会計上の仕訳構造はAccounting DomainのAccount MasterおよびJournal Entry Ruleに従う。

予約がキャンセルされた場合、未収金の消滅・免除・取消等をAccounting上のAdjustmentとして処理する。

---

# Check In and Revenue Recognition

Check In完了は仮売上振替の契機ではない。会計期間末に、対象Productionに紐づく既存の売上Journal Entry残高を仮売上へ振り替える。売上取引の金額は元の取引FactおよびPrice Snapshot等の既存仕様に従い、Check In時点で新たな売上・仮売上仕訳を生成しない。

基本Flow：

元の売上Journal Entry
    ↓
会計期間末
    ↓
仮売上へ振替
    ↓
Production決算確定
    ↓
正式な売上へ振替

Check In時点で未収金が存在する場合の消込は、実際の入金・決済Factに基づいて処理する。Check Inのみを理由に仮売上仕訳を生成しない。期末の仮売上振替では元の売上Journal Entryと二重計上にならないようにする。

例えば3,000円の予約について来場した場合：

Check In時点で仮売上振替仕訳は生成しない。期末の振替仕訳の具体的な貸借・相手勘定はAccount / Journal Entry仕様に従う。

実際にその場で現金・預金等を受領した場合は、Accounting上必要な入金処理を別途行い、未収金を消し込む。

実際の入金タイミングとRevenue Recognition Timingは同一である必要はない。

---

# No-Show

Reservationが成立していてもCheck Inされなかった場合、原則としてTicket Revenueは認識しない。

No-ShowとなったReservationについて、未収金をどう処理するかは、キャンセル規則・返金規則・免除規則等に従ってAccounting上で処理する。

仮売上への振替は会計期間末に行う。Check Inは仮売上振替の契機ではない。

---

# Ticket Amount Source

Revenue Recognitionに利用するTicket取引金額は、Reservation成立時に保存されたPrice Snapshotを基準とする。

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

同一CheckInCompleted Eventを複数回処理しても、同一Ticket Revenueを二重計上してはならない。

Accounting Processingは、Eventの一意識別情報等を利用してIdempotentに実行できる構造とする。

---

# Reversal and Adjustment

Ticket取引やCheck Inに関する取消・修正が発生した場合、既存Journal Entryを物理削除・直接書換えするのではなく、Accounting DomainのReversalまたはAdjustmentとして記録する。

元の会計Factとの関連を追跡できることを基本とする。

会計期間末の仮売上振替後に元となる売上取引やCheck Inが無効化された場合も、元のJournal Entryを直接削除せず、関連する仮売上振替および元仕訳を考慮したReversal / Adjustmentを生成する。

---

# Canonical Decision

Ticket Revenueの会計処理は以下を正式仕様とする。

> **Check In時点では仮売上へ振り替えない。会計期間末に決算未確定Productionの売上残高を仮売上へ振り替え、Production決算確定時に正式な売上へ振り替える。**

これにより、予約・来場Fact・正式な売上認識を区別する。

Reservationは「予約された」という事実を管理し、Check Inは「実際に来場した」という事実を管理する。Accountingは会計期間末を契機に仮売上へ振り替え、Production決算確定時に正式な売上へ振り替える。CheckInCompletedは仮売上振替の契機ではない。

---

# Related Policies

以下のDomain仕様は本Ruleを正として整合させる。

- Reservation Domain
- Check In Domain
- Ticket Domain
- Accounting Policy
- Journal Entry Domain
- Project Accounting Policy
- Production Accounting Policy

特にAccounting PolicyやTicket / Performance DomainにCheckInCompleted時点で正式な売上を認識すると記載されている場合は、本PolicyのCanonical Ruleに従い、会計期間末を仮売上への振替時点、Production決算確定時点を正式な売上への振替時点とする。CheckInCompletedは仮売上振替の契機ではない。
