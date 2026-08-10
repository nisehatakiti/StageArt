# StageArt Blueprint
# Domain Event : ReservationCreated

Version : 2.0

---

# Purpose

ReservationCreatedは、
Reservationが作成されたことを表すDomain Eventである。

Reservation DomainがReservationの作成を確定した後に発行する。

ReservationCreatedは、
「予約が作成された」というBusiness Eventを表現する。

ReservationCreated自身はHistoryを直接操作しない。

予約されたことと、
実際に来場したことは別のBusiness Eventとして扱う。

そのため、ReservationCreatedを契機として
Audience Historyは生成しない。

---

# Event

ReservationCreated

---

# Publisher

Reservation

Reservation DomainがEventを発行する。

---

# Trigger

以下のBusiness Actionによって発生する。

Reservation
    │
    │ Create
    ▼
ReservationCreated

Reservationの作成が正常に完了した場合に発行する。

---

# Event Payload

{
  "eventId": "event-005",
  "eventType": "ReservationCreated",
  "occurredAt": "2026-08-10T09:00:00+09:00",
  "reservationId": "reservation-001",
  "productionId": "production-001",
  "performanceId": "performance-001",
  "bookerId": "person-001",
  "handledParticipantId": "participant-001",
  "guestCount": 2,
  "createdBy": "person-002",
  "createdAt": "2026-08-10T09:00:00+09:00"
}

---

# Payload Definitions

| Field | Description |
|---|---|
| eventId | Eventを一意に識別するID |
| eventType | Event Type |
| occurredAt | Eventが発生した日時 |
| reservationId | 作成されたReservation |
| productionId | 予約対象となったProduction |
| performanceId | 予約対象となったPerformance |
| bookerId | 予約者であるPerson |
| handledParticipantId | 予約を担当するParticipant。未指定の場合はNULL |
| guestCount | Reservationに含まれる来場人数 |
| createdBy | Reservationを作成した主体 |
| createdAt | Reservationが作成された日時 |

---

# Booker

Bookerは予約者を表す。

BookerはReservationに必須である。

ReservationCreatedでは、
Bookerが誰であるかをEvent Payloadに記録する。

BookerとCreatedByは異なる概念である。

例えば、

観客Aが自分で予約した場合、

Booker
    = 観客A

CreatedBy
    = 観客A

となる。

Participant本人が観客Aの予約を代理入力した場合、

Booker
    = 観客A

CreatedBy
    = Participant

となる。

---

# Handled Participant

HandledParticipantは、
予約における「○○扱い」のParticipantを表す。

HandledParticipantは任意である。

指定されない場合はNULLとする。

HandledParticipantはBookerおよびCreatedByとは
異なる概念である。

HandledParticipantは、
Reservationの予約情報としてEvent Payloadに含める。

---

# Guest Count

GuestCountは、
Reservationに含まれる来場人数を表す。

Booker本人とCompanionを含めた人数を表す。

例えば、

Booker
    = 1名

Companion
    = 1名

GuestCount
    = 2名

となる。

GuestCountは、
チェックイン時の予約人数集計に使用する。

---

# Reservation Seat

座席指定があるReservationでは、
ReservationSeatが設定される。

ReservationCreatedでは、
作成時点のReservationSeatをReservationの一部として扱う。

ReservationSeatはReservationに属する情報であり、
ReservationCreatedではReservationとともに確定する。

人数変更によって座席変更が必要になった場合は、
ReservationUpdatedによって変更後の状態を表現する。

---

# Created By

CreatedByは、
Reservationを作成した主体を表す。

CreatedByはReservation作成時に設定する。

CreatedByはReservation作成後に変更しない。

CreatedByはBookerおよびHandledParticipantとは
独立して管理する。

---

# Created At

CreatedAtは、
Reservationが作成された日時を表す。

CreatedAtはReservation作成時に設定する。

CreatedAtは変更しない。

---

# Updated By / Updated At

ReservationCreatedでは、
Reservationの作成時点でUpdatedByおよびUpdatedAtも
初期値として設定される。

UpdatedBy
    = CreatedBy

UpdatedAt
    = CreatedAt

その後Reservationが変更された場合は、
ReservationUpdatedによって変更後の状態を表現する。

---

# History

ReservationCreatedではHistoryを生成しない。

予約したことと、
実際に観劇したことは異なるBusiness Eventである。

そのため、

ReservationCreated
    ↓
Historyなし

とする。

実際の来場は、

Reservation
    ↓
Check In
    ↓
ReservationCheckedIn
    ↓
Audience History

によって記録する。

---

# Event Flow

Reservation
    │
    │ Create
    ▼
ReservationCreated
    │
    ├── Notification
    ├── Other Business Process
    └── Historyなし

ReservationCreatedは、
予約作成後のBusiness Processを開始する契機として利用できる。

---

# Event Handler

ReservationCreatedを受信したEvent Handlerは、
必要に応じてReservationに関連するBusiness Processを実行する。

例）

ReservationCreated
        ↓
Notification
        ↓
予約確認通知

または、

ReservationCreated
        ↓
Other Business Process

Event HandlerはReservation Domainの内部実装を
直接操作しない。

---

# Idempotency

ReservationCreatedの処理は冪等であること。

同じeventIdを複数回受信した場合でも、
同一のBusiness Processを重複して実行しない。

ReservationCreated
eventId = event-005

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
Reservationの作成自体を取り消さない。

ReservationCreatedは発行済みEventとして保持し、
Event Handlerが再実行できる状態とする。

---

# Business Rules

- ReservationCreatedはReservation Domainが発行する。
- ReservationCreatedはReservation作成完了後に発行する。
- ReservationCreatedはReservation作成の事実を表現する。
- BookerはReservationに必須である。
- HandledParticipantは任意である。
- HandledParticipantがない場合はNULLとする。
- CreatedByはReservationを作成した主体を表す。
- CreatedByはReservation作成後に変更しない。
- CreatedAtはReservation作成日時を表す。
- UpdatedByの初期値はCreatedByとする。
- UpdatedAtの初期値はCreatedAtとする。
- GuestCountはReservationに含まれる来場人数を表す。
- ReservationSeatはReservationの一部として管理する。
- ReservationCreatedではAudience Historyを生成しない。
- 実際の来場はReservationCheckedInによって記録する。
- Event処理は冪等である。
- Event Handlerの失敗によってReservation作成を取り消さない。

---

# Design Principles

- Eventは発生したBusiness Eventを表現する。
- ReservationCreatedはReservation作成の事実を表現する。
- Event PublisherとEvent Handlerを分離する。
- Reservation DomainはHistory Domainへ直接依存しない。
- ReservationCreatedではAudience Historyを生成しない。
- ReservationCheckedInでAudience Historyを生成する。
- BookerとCreatedByを分離する。
- HandledParticipantとCreatedByを分離する。
- GuestCountはReservation作成時点の来場人数を表現する。
- ReservationSeatはReservationの一部として扱う。
- Reservation変更はReservationUpdatedで表現する。
- Event処理は冪等性を確保する。
- Event PayloadはEvent発生時点の事実を表現する。