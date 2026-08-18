# StageArt Blueprint

# Domain Consistency Policy : Reservation

Version : 1.0

---

# Purpose

本書はReservation Domainについて、現在のCanonical Domain Modelおよび確定済みのPerformance / Ticket / Reservation Capacity仕様との整合性を定義する。

既存のReservation.mdに記載された基本設計を維持しつつ、予約定員、Performance、Ticket、Price Snapshot、Check In、Accountingの責務境界を明確化する。

---

# Canonical Position

Reservationは、特定のPerformanceに対する観客の予約Factを表す。

基本構造：

Production
    ↓
Performance
    ↓
Reservation
    ↓
Ticket

ReservationはProductionではなく、必ず特定のPerformanceに所属する。

---

# Reservation Capacity

予約定員の適用単位はPerformanceとする。

Productionには標準予約定員を設定でき、Performance作成時にその値を継承する。

基本構造：

Production
    ↓
Standard Reservation Capacity
    ↓
Performance
    ↓
Performance Reservation Capacity
    ↓
Reservation

Performanceは、Productionから継承した定員を個別に変更できる。

Performanceごとの定員は、実際のReservation受付時に適用する。

---

# Capacity Inheritance

Productionの標準予約定員は、Performance作成時に初期値として継承する。

既存Performanceの定員は、Productionの標準定員を後から変更しても自動的に上書きしない。

例：

Production
    標準予約定員 = 100

Performance A
    100

Performance B
    80  ← 個別Override

Performance C
    100

Productionの標準値を120へ変更した場合でも、既存Performance A / B / Cの定員を自動変更してはならない。

新たに作成するPerformanceには、変更後の120を継承する。

---

# Capacity Responsibility

定員そのものの設定値はPerformanceに保持する。

Reservation Domainは、Performanceに設定された予約定員を使用して予約可否を判定する。

したがって、責務は以下のように分離する。

Performance
    = 予約定員の設定

Reservation
    = その定員を超えないよう予約を受け付けるBusiness Rule

Reservationが独自の別定員を保持して、Performanceの定員と二重管理してはならない。

---

# Capacity Counting

定員判定はReservationのGuestCountを人数として行う。

基本計算：

現在の予約人数
= 有効なReservationのGuestCount合計

予約可能人数
= Performance Capacity - 現在の予約人数

新規ReservationのGuestCountを加算した結果がPerformance Capacityを超える場合、通常のReservation作成を許可しない。

---

# Cancelled Reservation and Capacity

CANCELLEDとなったReservationは、予約定員の使用数から除外する。

例：

Performance Capacity = 100

Reservation A = 30
Reservation B = 20
Reservation C = 10 / CANCELLED

現在の予約人数 = 50

CANCELLEDのReservationは定員を消費しない。

Reservation自体は履歴として削除しない。

---

# Checked In Reservation and Capacity

CHECKED_INとなったReservationは、有効な予約として定員使用数に含める。

Check Inによって定員使用数から除外してはならない。

定員は「現在そのPerformanceに対して確保されている予約人数」を表すため、来場済みReservationも公演終了までは定員計算上の予約として扱う。

---

# No Show Reservation and Capacity

NO_SHOWとなったReservationは、定員を消費した予約Factとして保持する。

NO_SHOWへの変更によって過去の予約人数を再計算して定員を解放することはしない。

公演終了後の状態であるため、通常のReservation受付可否には影響しない。

---

# Capacity = 0

Capacity = 0は「無制限」を意味しない。

Capacity = 0の場合、通常のReservationは受付できない。

無制限を表す特殊値を設けるかどうかは、将来必要になった時点で別途定義する。

---

# Capacity Validation

Reservation作成・GuestCount変更時には、対象Performanceの現在の予約使用数と新しいGuestCountを合算して定員を判定する。

Reservation更新によってGuestCountを増加させる場合も、定員を超えてはならない。

GuestCountを減少させる変更は、定員を増加させることなく許可できる。

CHECKED_INおよびCANCELLEDのReservationは通常Update対象外であるため、GuestCount変更による定員再計算を行わない。

---

# Concurrent Reservation

同一Performanceに対する複数のReservationが同時に作成された場合でも、定員を超過するReservationを成立させてはならない。

定員判定とReservation成立は、定員を超過しないことを保証できる単位で一貫して処理する。

具体的なDatabase Lock、Transaction、Concurrency Controlの方式はInfrastructure / Application Architectureで定義する。

---

# Performance Relationship

Reservationは必ず一つのPerformanceに所属する。

Reservation作成後にPerformanceを変更してはならない。

別Performanceへの変更が必要な場合は、既存ReservationをCANCELLEDとし、新しいPerformanceに対するReservationを作成する。

これにより、過去の予約Factと定員計算の整合性を維持する。

---

# Ticket Relationship

Reservationは、対象Performanceで利用するTicketを参照する。

Ticketの正本はProduction側にある。

ReservationはTicketの現在の販売条件を変更しない。

PerformanceごとのTicket Availabilityが設定されている場合、Reservation作成時には対象Performanceで利用可能なTicketであることを検証する。

Ticket Availabilityの詳細仕様はTicket Domainで定義する。

---

# Price Snapshot

Reservation成立時には、その時点のTicket PriceをPrice Snapshotとして保持する。

基本構造：

Ticket
    ↓
Reservation
    ↓
Price Snapshot

Ticketの現在Priceが後から変更されても、既存ReservationのPrice Snapshotを変更しない。

Price Snapshotは予約時点の取引Factであり、Ticketの現在値から再計算してはならない。

---

# Booker

Bookerは予約者を表す。

BookerとCreatedByは別概念とする。

例えば、観客本人がBookerであり、劇団スタッフが代理入力のCreatedByである状態を許可する。

---

# Guest Count

GuestCountはReservationによって確保される人数を表す。

Version 1.0では同行者を独立Person / Companionとして管理しない。

GuestCountは、以下に利用する。

- Reservation Capacity
- 受付時の予約人数集計
- 公演回ごとの集客集計

同行者を個別にCheck Inする機能は初期仕様では提供しない。

---

# Reservation Status

基本状態：

- RESERVED
- CHECKED_IN
- CANCELLED
- NO_SHOW

RESERVEDは有効な予約状態である。

CHECKED_INは来場受付完了を表す。

CANCELLEDはキャンセルされた予約Factを保持する。

NO_SHOWは予約が存在したが来場が確認されなかった状態を表す。

---

# Status and Capacity

定員計算上の扱いは以下とする。

| Status | Capacity Count |
|---|---|
| RESERVED | 含む |
| CHECKED_IN | 含む |
| CANCELLED | 含まない |
| NO_SHOW | 含む（公演終了後の状態） |

NO_SHOWについては通常、公演終了後に判定されるため、Reservation受付中の定員判定には実質的に影響しない。

---

# Reservation Update

Check In前のRESERVED Reservationは、必要な範囲で変更できる。

変更可能な情報には、原則として以下を含む。

- Booker
- Ticket
- GuestCount

Performanceは変更できない。

Price Snapshotは変更できない。

Ticket変更によってPrice Snapshotを自動的に再計算してはならない。

GuestCountを変更する場合は、変更後の人数がPerformance Capacityを超えないことを検証する。

---

# Cancellation

Check In前のReservationはCANCELLEDへ変更できる。

Reservationそのものを削除してはならない。

CANCELLEDとなったReservationはCapacity Countから除外する。

---

# Check In

Check InはReservation単位で行う。

受付中のPerformanceとReservationのPerformanceが一致する場合のみCheck Inを許可する。

GuestCountが複数であっても、初期仕様ではReservation全体を一つのCheck Inとして扱う。

Check In完了後はCHECKED_INとなり、通常のReservation変更を行わない。

---

# Accounting

Reservation DomainはJournal Entryを管理しない。

CheckInCompletedを契機として、Ticket Revenue等の会計連携をAccounting Domainへ渡す。

基本Flow：

Reservation
    ↓
Check In
    ↓
CheckInCompleted
    ↓
Ticket Revenue
    ↓
Accounting Journal Entry

Accounting上の正本はJournal Entryであり、Reservationが会計Factを二重管理しない。

---

# History

予約しただけでは観劇履歴を生成しない。

CheckInCompletedを契機として、必要なHistory Domainが観劇履歴を生成する。

基本Flow：

Reservation
    ↓
CheckInCompleted
    ↓
History

---

# Deletion

Reservationは、予約Factおよび関連する会計・履歴の整合性を維持するため、物理削除を基本としない。

キャンセルはCANCELLEDへの状態変更で表現する。

過去のReservationを削除してCapacity Countや会計Factを改変してはならない。

---

# Canonical Relationship Summary

```text
Organization
    ↓
Project
    ↓
Production
    ├── Venue
    ├── Ticket
    └── Performance
          ↓
       Reservation
          ├── Booker
          ├── Ticket Reference
          ├── Price Snapshot
          └── Guest Count
```

Capacityについては、Productionの標準値をPerformanceが継承し、Performanceの定員をReservationが受付判定に利用する。

---

# Design Principle

Reservationは「誰がどの公演回に何人でどのTicketを予約したか」という取引Factを管理する。

定員の設定値はPerformanceに置き、Reservationはその定員を超えないことを保証する。

Ticketの販売条件とReservation成立時点の取引条件を分離し、Price Snapshotによって過去Factを保持する。

Reservationの変更・キャンセル・Check Inによって過去のFactを削除・改変せず、状態遷移によって履歴を保持する。
