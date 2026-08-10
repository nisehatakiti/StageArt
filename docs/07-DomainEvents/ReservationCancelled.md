# StageArt Blueprint
# Domain Event : ReservationCancelled

Version : 1.0

---

# Purpose

ReservationCancelledは、
Reservationがキャンセルされたことを表すDomain Eventである。

Reservation DomainがReservationのキャンセルを確定した後に発行する。

ReservationCancelledは、
「予約がキャンセルされた」というBusiness Eventを表現する。

Reservation自体は削除しない。

ReservationCancelled自身はHistoryを直接操作しない。

キャンセルされた予約は、
実際に観劇した予約ではないため、
Audience Historyは生成しない。

---

# Event

ReservationCancelled

---

# Publisher

Reservation

Reservation DomainがEventを発行する。

---

# Trigger

以下のBusiness Actionによって発生する。

Reservation
    │
    │ Cancel
    ▼
ReservationCancelled

Reservationのキャンセルが正常に完了した場合に発行する。

---

# Cancellation Timing

Reservationのキャンセルは、
Check In前に限り可能とする。

ReservationStatusが、

RESERVED

の場合はキャンセルできる。

ReservationStatusが、

CHECKED_IN

の場合はキャンセルできない。

---

# Event Payload

{
  "eventId": "event-007",
  "eventType": "ReservationCancelled",
  "occurredAt": "2026-08-10T16:00:00+09:00",
  "reservationId": "reservation-001",
  "productionId": "production-001",
  "performanceId": "performance-001",
  "bookerId": "person-001",
  "handledParticipantId": "participant-001",
  "guestCount": 2,
  "reservationSeats": [
    "A-12",
    "A-13"
  ],
  "status": "CANCELLED",
  "updatedBy": "person-003",
  "updatedAt": "2026-08-10T16:00:00+09:00"
}

---

# Payload Definitions

| Field | Description |
|---|---|
| eventId | Eventを一意に識別するID |
| eventType | Event Type |
| occurredAt | Eventが発生した日時 |
| reservationId | キャンセルされたReservation |
| productionId | 予約対象となったProduction |
| performanceId | 予約対象となったPerformance |
| bookerId | 予約者であるPerson |
| handledParticipantId | Reservationに設定されたParticipant。未指定の場合はNULL |
| guestCount | キャンセル時点のReservation人数 |
| reservationSeats | キャンセル時点の予約座席一覧 |
| status | キャンセル後のReservation Status |
| updatedBy | キャンセルを実行した主体 |
| updatedAt | キャンセルが完了した日時 |

---

# Status Transition

キャンセル前のReservationは、

RESERVED

である。

キャンセルによって、

RESERVED
    ↓
CANCELLED

へ変更する。

CANCELLEDとなったReservationは削除しない。

---

# Updated By

UpdatedByは、
Reservationのキャンセルを実行した主体を表す。

キャンセルを実行した認証済み利用者を設定する。

CreatedByは変更しない。

---

# Updated At

UpdatedAtは、
Reservationがキャンセルされた日時を表す。

キャンセル完了時に更新する。

---

# Reservation Seat

ReservationSeatは、
Reservationに紐づく予約済み座席を表す。

Reservationがキャンセルされた場合、
その座席は予約状態から解放される。

ただし、ReservationSeatそのものを
独立したDomainとして削除するわけではない。

Reservation Aggregateを通して、
キャンセル後の座席状態を管理する。

ReservationSeatはCheck Inの対象ではない。

Check InはReservation単位で行う。

---

# Check In Restriction

CHECKED_INのReservationに対して、
ReservationCancelledを発行してはならない。

CHECKED_INのReservationは、
来場実績が確定した状態であるため、
通常の予約キャンセルを行わない。

---

# Cancelled Reservation

CANCELLEDとなったReservationは、
通常の予約として扱わない。

CANCELLEDのReservationに対して、

- Check In
- 通常のReservation Update
- 再キャンセル

を行わない。

Reservation自体は保持するため、
予約が存在した事実およびキャンセルされた事実を確認できる。

---

# History

ReservationCancelledでは、
Audience Historyを生成・更新しない。

予約のキャンセルは、
観劇実績を意味しない。

そのため、

ReservationCancelled
    ↓
Historyなし

とする。

Audience Historyは、

Reservation
    ↓
ReservationCheckedIn
    ↓
Audience History

によって生成される。

---

# Event Flow

Reservation
    │
    │ Cancel
    ▼
ReservationCancelled
    │
    ├── Notification
    ├── Seat Availability Update
    ├── Other Business Process
    └── Historyなし

ReservationCancelledは、
キャンセル後のBusiness Processを開始する契機として利用できる。

---

# Event Handler

ReservationCancelledを受信したEvent Handlerは、
必要に応じてReservationキャンセルに関連するBusiness Processを実行する。

例）

ReservationCancelled
        ↓
Notification
        ↓
予約キャンセル確認通知

または、

ReservationCancelled
        ↓
Seat Availability Update
        ↓
解放された座席を予約可能状態へ反映

Event Handlerは、
Reservation Domainの内部実装を直接操作しない。

---

# Idempotency

ReservationCancelledの処理は冪等であること。

同じeventIdを複数回受信した場合でも、
同一のBusiness Processを重複して実行しない。

ReservationCancelled
eventId = event-007

1回目
    ↓
Business Process実行

2回目
    ↓
処理済み
    ↓
Business Process実行しない

---

# Failure Handling

Event Handlerによる後続処理に失敗した場合でも、
Reservationのキャンセル自体を取り消さない。

ReservationCancelledは発行済みEventとして保持し、
Event Handlerが再実行できる状態とする。

---

# Business Rules

- ReservationCancelledはReservation Domainが発行する。
- ReservationCancelledはReservationのキャンセル完了後に発行する。
- ReservationはCheck In前であればキャンセルできる。
- CHECKED_INのReservationはキャンセルできない。
- CANCELLEDのReservationは再度キャンセルしない。
- CANCELLEDのReservationはCheck Inできない。
- CANCELLEDのReservationは通常のReservation Updateを行わない。
- Reservation自体は削除しない。
- CreatedByは変更しない。
- UpdatedByはキャンセルを実行した主体へ更新する。
- UpdatedAtはキャンセル日時へ更新する。
- キャンセルされたReservationの座席は予約状態から解放する。
- ReservationSeatは独立したDomainとして削除しない。
- ReservationSeatはCheck Inの対象ではない。
- Check InはReservation単位で行う。
- ReservationCancelledではAudience Historyを生成・更新しない。
- Event処理は冪等である。
- Event Handlerの失敗によってReservationのキャンセルを取り消さない。

---

# Design Principles

- Eventは発生したBusiness Eventを表現する。
- ReservationCancelledはReservationキャンセルの事実を表現する。
- Reservationを物理的に削除しない。
- キャンセル状態をReservationStatusで管理する。
- CreatedByとUpdatedByを分離する。
- CreatedAtとUpdatedAtを分離する。
- Check In済みReservationはキャンセルしない。
- キャンセルされたReservationをCheck Inしない。
- キャンセルされたReservationを通常の予約として更新しない。
- ReservationSeatはReservationに属する。
- キャンセルによってReservationSeatを予約状態から解放する。
- ReservationSeatは個別にCheck Inしない。
- Check InはReservation単位で行う。
- ReservationCancelledではHistoryを管理しない。
- Reservation DomainはHistory Domainへ直接依存しない。
- Event HandlerをPublisherから分離する。
- Event処理は冪等性を確保する。
- Event PayloadはEvent発生時点の事実を表現する。
