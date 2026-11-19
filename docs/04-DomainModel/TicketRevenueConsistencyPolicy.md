# StageArt Blueprint

# Domain Consistency Policy : Ticket Revenue / Accounting

Version : 1.2

---

# Purpose

Ticket RevenueとAccounting Domainの責務境界を整理し、Reservation、Check In、Journal Entry、およびOrganization / Project / Production Accountingとの関係を定義する。

本書は、Accounting PolicyおよびCheck In Domainを横断したTicket RevenueのCanonical Ruleを定義する。

---

# Canonical Revenue Recognition Rule

**Ticket RevenueはCheck In時点で成立・認識する。**

Reservation成立時点では、Ticket Revenueはまだ成立していない。

Reservation成立後、実際の来場が確認されるCheck Inをもって、Ticket RevenueをRevenueとして認識する。

基本Flow：

Reservation成立
    ↓
未収金 / 予約Fact
    ↓
Check In
    ↓
Ticket Revenue認識
    ↓
Journal Entry

したがって、予約済みで未来場のTicketは、Revenueとして認識せず、未収金として扱う。

ただし、受付でNO_SHOWとして確定したReservationは、本Ruleの確定済み例外としてTicket Revenueを認識する（後述「No-Show」）。

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
- Ticket Revenue認識の契機

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

# Reservation and Accounts Receivable

Reservation成立時点では、Price Snapshotに基づく予約金額を未収金の対象として扱う。

例えば3,000円のTicket予約が成立した場合：

未収金 3,000 / 予約に対応する未収金認識

ただし、この時点ではTicket Revenueを認識しない。

実際の会計上の仕訳構造はAccounting DomainのAccount MasterおよびJournal Entry Ruleに従う。

予約がキャンセルされた場合、未収金の消滅・免除・取消等をAccounting上のAdjustmentとして処理する。

---

# Check In and Revenue Recognition

Check Inが正常に完了した時点で、ReservationのPrice Snapshotを基準としてTicket Revenueを認識する。

基本Flow：

Reservation
    ↓
Price Snapshot
    ↓
Check In
    ↓
CheckInCompleted
    ↓
Ticket Revenue Recognition
    ↓
Journal Entry

Check In時点で未収金が存在する場合は、Ticket Revenueの認識と同時に未収金を売上へ振り替える。

例えば3,000円の予約について来場した場合：

Ticket Revenue 3,000 / 未収金 3,000

実際にその場で現金・預金等を受領した場合は、Accounting上必要な入金処理を別途行い、未収金を消し込む。

実際の入金タイミングとRevenue Recognition Timingは同一である必要はない。

---

# No-Show

以下を確定仕様とする（Version 1.2で旧「No-ShowではTicket Revenueを認識しない」を置き換え）。

- NO_SHOWはTicket Revenue（売上実績）に含める。
- NO_SHOWは販売実績に含める。
- NO_SHOWはTicket Backの計算対象に含める。
- NO_SHOWはノルマ実績に含める。
- NO_SHOWは実来場者数に含めない。
- NO_SHOWのReservationはCapacityを消費し続ける。
- NO_SHOW後の通常の予約取消は行えない。
- 通常の予約取消によってNO_SHOWの売上Journal Entryを自動的に取り消さない。
- 確定済みJournal Entryの訂正が必要な別のケースでは、後述「Reversal and Adjustment」に従う。

NO_SHOWの確定は、受付でReservationをNO_SHOWとして記録した時点とする。Accountingが有効な場合、その時点でReservation単位のTicket Revenue Journal Entryを生成し確定する。NO_SHOWは来場Factではないため、Check In実績は生成しない。

売上認識と実来場者数は別の概念として扱う。売上計上済みであることを理由に、NO_SHOWを実来場者数へ加算してはならない。

Check Inされず、NO_SHOWとしても記録されていないReservationについては、原則どおりTicket Revenueを認識しない。

Revenue Recognitionは、原則としてCheck Inという来場Factに基づき、NO_SHOWの確定をその例外とする。

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

NO_SHOWによるTicket Revenueも同様に、Reservation単位で高々一件とし、同一ReservationのNO_SHOW確定を再処理しても二重計上してはならない。

---

# Reversal and Adjustment

Ticket取引やCheck Inに関する取消・修正が発生した場合、既存Journal Entryを物理削除・直接書換えするのではなく、Accounting DomainのReversalまたはAdjustmentとして記録する。

元の会計Factとの関連を追跡できることを基本とする。

Revenue認識後にCheck Inが無効化された場合も、元のRevenue Journal Entryを直接削除せず、必要なReversal / Adjustmentを生成する。

---

# Canonical Decision

Ticket Revenueの認識タイミングは以下を正式仕様とする。

> **Reservation成立時点では未収金。Check In時点でTicket Revenue成立・認識。NO_SHOWの確定時点でもTicket Revenueを認識する（実来場者数には含めない）。**

これにより、予約と売上を明確に分離する。

Reservationは「予約された」という事実を管理し、Check Inは「実際に来場した」という事実を管理する。AccountingはCheckInCompletedを契機としてRevenueを認識する。

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

特にAccounting PolicyにReservation成立時のTicket Revenue認識が記載されている場合は、本PolicyのCanonical Ruleを優先する。
