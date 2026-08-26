# StageArt Blueprint

# Domain Consistency Policy : Check In

Version : 1.0

---

# Purpose

Check In Domainについて、Reservation / Performance / Ticket / Accountingの現在のCanonical仕様との整合性を定義する。

既存のCheckIn.mdの基本設計を維持し、後から確定した仕様を優先する。

---

# Canonical Position

Check Inは、特定Performanceに対するReservationの来場受付Factである。

基本構造：

Production
    ↓
Performance
    ↓
Reservation
    ↓
Check In

Check InはReservationを正本とし、Reservationそのものを置換しない。

---

# Performance Scope

Check Inは必ず一つのPerformanceを対象とする。

受付時には、受付中PerformanceとReservation.Performanceが一致することを検証する。

異なるPerformanceへのCheck Inは許可しない。

PerformanceにはVenueを直接持たせず、VenueはProductionに紐づく。

---

# Capacity Boundary

予約定員の設定はPerformanceの責務である。

Productionの標準予約定員をPerformanceが継承し、PerformanceごとにOverrideできる。

Check Inは定員を設定・変更するDomainではない。

Reservation作成時の定員判定はReservation側で行う。

Check Inは、成立済みReservationを来場受付する。

---

# Check In Target

Check Inの対象はReservationである。

ReservationのGuestCountが複数であっても、初期仕様ではReservation単位で一括Check Inする。

同行者を個別のCheck In Factとして管理しない。

---

# Validation

Check In実行前に、少なくとも以下を検証する。

- Reservationが存在する
- ReservationがCANCELLEDではない
- ReservationのPerformanceと受付中Performanceが一致する
- 既にCheck In済みではない

同一Reservationに対する二重Check Inは成立させない。

---

# Status

Check Inは少なくとも以下の状態を持つ。

- COMPLETED
- REVERSED

COMPLETEDは受付完了を表す。

REVERSEDは誤受付等による管理上の取消を表す。

Check In Factは物理削除しない。

---

# Reservation Synchronization

Check Inが正常完了した場合、対象ReservationのStatusをCHECKED_INへ更新する。

Check In取消時のReservation状態についてはReservation Domainのルールに従う。

Check In DomainがReservationの履歴を独自に二重管理しない。

---

# Reception Operator

Check Inには受付を実行したPersonを記録する。

- Checked In By
- Checked In At

受付担当者は、対象Production / Performanceについて適切な権限を持つ必要がある。

Organization AdministratorまたはProduction Scopeで必要なRole / Permissionを持つPersonのみ実行できる。

---

# Methods

初期仕様では以下の受付方法を利用できる。

- QR Code
- Reservation Number
- Booker Name
- Manual Selection

受付方法が異なっても、生成されるCheck In Factは同一の概念として扱う。

QR Ticketは識別・認証手段であり、Check In Factそのものではない。

---

# Event

COMPLETEDへの遷移時にCheckInCompletedを発行する。

CheckInCompletedは、他Domainが来場完了を検知するためのBusiness Eventである。

---

# History Boundary

Check In Domainは観劇履歴を直接管理しない。

History DomainはCheckInCompletedを利用して観劇履歴を生成できる。

予約だけでは観劇履歴を生成しない。

---

# Accounting Boundary

Check In DomainはJournal Entryを直接生成・変更・削除しない。

CheckInCompletedを契機としてAccounting DomainがTicket Revenue Recognitionを処理する。

基本構造：

Check In
    ↓
CheckInCompleted
    ↓
Ticket Revenue Recognition
    ↓
Journal Entry

---

# Revenue Amount

売上認識に使用する金額はReservationのPrice Snapshotを基礎とする。

Ticketの現在価格を参照して過去Reservationの売上額を再計算しない。

GuestCountが複数の場合も、Reservationに記録された実際の取引金額を基礎とし、単純なTicket Price × GuestCountで再計算しない。

---

# Accounting Idempotency

同一CheckInCompletedを複数回処理しても、同一Ticket Revenueを二重計上してはならない。

Accounting Domain側でEvent識別情報等を利用して冪等処理する。

---

# Accounting Reversal

COMPLETEDのCheck InがREVERSEDとなり、既に会計計上済みの場合は、Accounting Domainの取消・修正ルールに従って処理する。

Check In Domainは既存Journal Entryを直接変更しない。

---

# Public / Reservation Boundary

Publicページ上の予約受付状態と、Check In状態は別概念である。

予約受付終了、満席、Performance終了等によって新規Reservationができない場合でも、既存ReservationのCheck In Factは保持される。

---

# Data Integrity

Check Inは履歴Factとして扱い、物理削除によって過去の来場事実を失わせない。

誤受付はREVERSED等の状態変更で訂正し、既存Factとの追跡可能性を維持する。

---

# Canonical Responsibility Summary

| Domain | Responsibility |
|---|---|
| Performance | 公演回、予約定員の適用単位 |
| Reservation | 予約Fact、GuestCount、Price Snapshot、予約状態 |
| Check In | 来場受付Fact、受付担当者、受付日時 |
| Ticket | Productionの販売条件・価格 |
| History | 観劇履歴 |
| Accounting | 売上認識、Journal Entry、会計取消 |

Check Inは、これらの責務を重複して保持しない。

---

# Design Principle

**Reservation = 予約したFact**

**Check In = 実際に受付したFact**

**CheckInCompleted = 受付完了を他Domainへ伝えるEvent**

**Journal Entry = 会計Factの正本**

この責務分離をCanonical仕様とする。
