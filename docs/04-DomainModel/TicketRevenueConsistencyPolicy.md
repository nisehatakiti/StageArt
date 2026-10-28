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

予約のみでは売上・入金を計上しない。標準運用では、当日Check In時に代金を実際に受領したことをPayment Factとして記録し、その時点で入金とTicket Revenueを同時に計上する。Check Inだけで代金を受領していない場合は計上しない。仮売上への振替は会計期間末の別処理である。

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

したがって、標準運用では予約済みで未来場・未入金のTicketは、予約があるという理由だけで売上・入金・未収金を計上しない。実際に受取権利が別途確定する契約・運用がある場合の未収金処理は、その条件を定めた別仕様に従う。

---

# Domain Responsibility

Reservation Domain：

- 予約Fact
- Guest Count
- Price Snapshot
- Reservation Status
- 予約金額の確定情報（Price Snapshot）。予約金額が確定したことだけをもって未収金を計上しない。

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

Reservation成立時点ではPrice Snapshotを保存するが、予約成立だけでは売上・入金・未収金を計上しない。

標準運用では、当日Check In時に3,000円を実際に受領した場合、その受領Factに基づき入金とTicket Revenueを同時に計上する。具体的なDebit / Creditおよび決済手段に対応するAccountはAccounting DomainのAccount MasterおよびJournal Entry Ruleに従う。

予約キャンセルは予約状態の変更として記録する。未収金が実際に計上されているケースに限り、必要な取消・免除等をAccounting上のAdjustmentとして処理する。

---

# Check In and Revenue Recognition

CheckInCompleted自体は会計仕訳を発生させない。ただし、当日Check In時に代金を実際に受領した場合は、Payment Factを記録し、その受領と同時に入金およびTicket RevenueのJournal Entryを計上する。来場記録だけで受領がない場合は計上しない。会計期間末には、対象Productionの既存売上Journal Entry残高を仮売上へ振り替える。

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

当日Check In時に代金を受領した場合、入金とTicket Revenueを同じ取引として同時に記録する。すでに未収金が計上されている例外ケースでは、実際の入金・決済Factに基づいて未収金を消し込む。CheckInCompleted自体を仕訳生成の根拠にせず、Payment Factを根拠とする。期末の仮売上振替は既存売上Journal Entryを対象に行い、二重計上しない。

例えば3,000円の予約について来場した場合：

Check In時点で仮売上振替仕訳は生成しない。期末の振替仕訳の具体的な貸借・相手勘定はAccount / Journal Entry仕様に従う。

標準運用では、その場で現金等を受領した時点で入金とTicket Revenueを同時に計上する。未収金が計上済みの例外ケースでは、その入金によって未収金を消し込む。事前決済等で入金と売上のタイミングが異なる運用を追加する場合は、別途Payment / Revenue Recognition仕様を定義する。

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

CheckInCompletedの再処理で来場Factを重複生成してはならない。入金・売上仕訳はPayment Factを根拠とし、同一Payment Factの再処理で二重計上してはならない。Accounting ProcessingはPayment Factの一意識別情報を利用してIdempotentに実行する。

---

# Reversal and Adjustment

Ticket取引やCheck Inに関する取消・修正が発生した場合、既存Journal Entryを物理削除・直接書換えするのではなく、Accounting DomainのReversalまたはAdjustmentとして記録する。

元の会計Factとの関連を追跡できることを基本とする。

会計期間末の仮売上振替後に元となる売上取引やCheck Inが無効化された場合も、元のJournal Entryを直接削除せず、関連する仮売上振替および元仕訳を考慮したReversal / Adjustmentを生成する。

---

# Canonical Decision

Ticket Revenueの会計処理は以下を正式仕様とする。

> **予約だけでは売上・入金を計上しない。標準運用では当日Check In時に実際に代金を受領した時点で、入金とTicket Revenueを同時に計上する。会計期間末には決算未確定Productionの既存売上残高を仮売上へ振り替え、Production決算確定時に正式な売上へ振り替える。**

Reservationは予約Fact、CheckInCompletedは来場Fact、Payment Factは実際の代金受領Factとして区別する。CheckInCompletedだけでは売上・入金仕訳を生成せず、実際の受領を示すPayment Factに基づいて計上する。

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
