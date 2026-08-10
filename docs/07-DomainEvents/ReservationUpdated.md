# StageArt Blueprint
# Domain Event : ReservationUpdated

Version : 1.0

---

# Purpose

ReservationUpdatedは、
既存のReservationが変更されたことを表すDomain Eventである。

Reservation DomainがReservationの変更を確定した後に発行する。

ReservationUpdatedは、
「予約内容が変更された」というBusiness Eventを表現する。

ReservationUpdated自身はHistoryを直接操作しない。

予約内容の変更は、
観劇実績の変更を意味しない。

そのため、ReservationUpdatedを契機として
Audience Historyは生成・更新しない。

---

# Event

ReservationUpdated

---

# Publisher

Reservation

Reservation DomainがEventを発行する。

---

# Trigger

以下のBusiness Actionによって発生する。

Reservation
    │
    │ Update
    ▼
ReservationUpdated

Reservationの変更が正常に完了した場合に発行する。

---

# Update Timing

Reservationの変更は、
Check In前に限り可能とする。

ReservationStatusが、

RESERVED

である場合は変更できる。

ReservationStatusが、

CHECKED_IN

の場合は変更できない。

ReservationStatusが、

CANCELLED

の場合も通常のReservation更新は行わない。

---

# Updateable Information

Check In前であれば、
以下のReservation情報を変更できる。

- Booker
- HandledParticipant
- Companion
- GuestCount
- ReservationSeat
- TicketType
- Memo

変更対象はReservation Aggregateを通して管理する。

---

# Event Payload

{
  "eventId": "event-006",
  "eventType": "ReservationUpdated",
  "occurredAt": "2026-08-10T15:30:00+09:00",
  "reservationId": "reservation-001",
  "productionId": "production-001",
  "performanceId": "performance-001",
  "bookerId": "person-001",
  "handledParticipantId": "participant-001",
  "guestCount": 3,
  "reservationSeats": [
    "A-12",
    "A-13",
    "B-12"
  ],
  "status": "RESERVED",
  "updatedBy": "person-003",
  "updatedAt": "2026-08-10T15:30:00+09:00"
}

---

# Payload Definitions

| Field | Description |
|---|---|
| eventId | Eventを一意に識別するID |
| eventType | Event Type |
| occurredAt | Eventが発生した日時 |
| reservationId | 変更されたReservation |
| productionId | 予約対象となったProduction |
| performanceId | 予約対象となったPerformance |
| bookerId | 予約者であるPerson |
| handledParticipantId | 予約に設定されたParticipant。未指定の場合はNULL |
| guestCount | 変更後のReservation人数 |
| reservationSeats | 変更後の予約座席一覧 |
| status | 変更後のReservation Status |
| updatedBy | Reservationを変更した主体 |
| updatedAt | Reservationが変更された日時 |

---

# Updated By

UpdatedByは、
Reservationを最後に変更した主体を表す。

Reservation変更を実行した認証済み利用者を設定する。

例えば、

CreatedBy
    = Participant 山田

UpdatedBy
    = 制作スタッフ 佐藤

という状態を許可する。

CreatedByは変更しない。

UpdatedByはReservationが変更されるたびに更新する。

---

# Updated At

UpdatedAtは、
Reservationが最後に変更された日時を表す。

Reservation変更完了時に更新する。

---

# Guest Count Change

Reservationの人数変更は、
ReservationUpdatedとして扱う。

例えば、

2名
    ↓
3名

への変更が発生した場合、

GuestCount
    2 → 3

としてReservationを更新する。

GuestCountの変更後は、
ReservationSeatとの整合性を確保する。

---

# Reservation Seat Adjustment

座席指定があるPerformanceでは、
GuestCount変更に伴ってReservationSeatも調整する。

例えば、

2名

A-12
A-13

だったReservationを、

3名

へ変更する場合、
3名分の座席を確保する。

追加席を確保する際に、
連続した座席を確保できない可能性がある。

そのため、

2名
A-12 / A-13

から、

3名
A-12 / A-13 / B-12

のように、
連席ではない状態になる可能性がある。

座席指定公演では、
人数変更によって連席を確保できない場合があることを
予約者へ事前に告知する。

GuestCountとReservationSeatは、
整合性が確保された状態でReservationUpdatedを発行する。

---

# Reservation Seat State

ReservationSeatは、
Reservationに紐づく予約済み座席を表す。

ReservationSeatは、
Check Inの対象ではない。

Check InはReservation単位で行う。

ReservationUpdatedでは、
予約変更後のReservationSeatの状態を記録する。

---

# Handled Participant Change

HandledParticipantは、
Reservationにおける「○○扱い」を表す。

Check In前であれば、
HandledParticipantを変更できる。

例えば、

HandledParticipant
    = 山田

から、

HandledParticipant
    = 佐藤

へ変更することができる。

変更後のHandledParticipantを
ReservationUpdatedのPayloadに記録する。

HandledParticipantの変更によって、
Historyを生成・更新しない。

---

# Booker Change

Check In前であれば、
Bookerを変更できる。

Bookerの変更後は、
変更後のBookerをReservationUpdatedのPayloadに記録する。

Bookerの変更によって、
Audience Historyを生成・更新しない。

Audience Historyは、
ReservationCheckedInを契機として生成される。

---

# Companion Change

Check In前であれば、
Companionを変更できる。

Companionの追加・削除によって、
GuestCountも必要に応じて変更する。

GuestCountとCompanionの整合性を確保した上で、
ReservationUpdatedを発行する。

---

# Ticket Type Change

Check In前であれば、
TicketTypeを変更できる。

変更後のTicketTypeを
ReservationUpdatedのPayloadに記録する。

---

# Check In Restriction

CHECKED_INのReservationに対して、
ReservationUpdatedを発行してはならない。

Check In済みReservationの内容を変更する必要がある場合は、
Reservationを直接変更するのではなく、
別途管理上の訂正処理を検討する。

通常のReservation業務では、
Check In前にすべての予約内容を確定する。

---

# Check In Preparation

ReservationUpdatedは、
Check In前の予約内容を正しい状態へ確定するために使用できる。

例えば、

予約時

GuestCount
    = 2

ReservationSeat
    = A-12 / A-13

来場時に、

実際には3名

であることが判明した場合、

受付担当者がCheck In前に、

GuestCount
    = 3

ReservationSeat
    = A-12 / A-13 / B-12

へ修正する。

その後、

ReservationUpdated
    ↓
Check In
    ↓
ReservationCheckedIn

という順序で処理する。

---

# History

ReservationUpdatedではHistoryを生成・更新しない。

予約内容が変更されたことは、
観劇実績の変更を意味しない。

そのため、

ReservationUpdated
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
    │ Update
    ▼
ReservationUpdated
    │
    ├── Notification
    ├── Other Business Process
    └── Historyなし

ReservationUpdatedは、
予約変更後のBusiness Processを開始する契機として利用できる。

---

# Event Handler

ReservationUpdatedを受信したEvent Handlerは、
必要に応じてReservation変更に関連するBusiness Processを実行する。

例）

ReservationUpdated
        ↓
Notification
        ↓
予約変更確認通知

Event Handlerは、
Reservation Domainの内部実装を直接操作しない。

---

# Idempotency

ReservationUpdatedの処理は冪等であること。

同じeventIdを複数回受信した場合でも、
同一のBusiness Processを重複して実行しない。

ReservationUpdated
eventId = event-006

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
Reservationの変更自体を取り消さない。

ReservationUpdatedは発行済みEventとして保持し、
Event Handlerが再実行できる状態とする。

---

# Business Rules

- ReservationUpdatedはReservation Domainが発行する。
- ReservationUpdatedはReservation変更完了後に発行する。
- Reservationの変更はCheck In前に限り可能とする。
- CHECKED_INのReservationは変更できない。
- CANCELLEDのReservationは通常のReservation更新を行わない。
- CreatedByは変更しない。
- UpdatedByは変更を実行した主体へ更新する。
- UpdatedAtは変更日時へ更新する。
- GuestCount変更時はReservationSeatとの整合性を確保する。
- 座席指定公演では人数変更によって連席を確保できない場合がある。
- 連席を確保できない可能性があることを予約者へ事前に告知する。
- ReservationSeatはReservationに属する。
- ReservationSeatは個別にCheck Inしない。
- Check InはReservation単位で行う。
- HandledParticipantはCheck In前であれば変更できる。
- BookerはCheck In前であれば変更できる。
- CompanionはCheck In前であれば変更できる。
- TicketTypeはCheck In前であれば変更できる。
- ReservationUpdatedではAudience Historyを生成・更新しない。
- Audience HistoryはReservationCheckedInを契機として生成する。
- Event処理は冪等である。
- Event Handlerの失敗によってReservation変更を取り消さない。

---

# Design Principles

- Eventは発生したBusiness Eventを表現する。
- ReservationUpdatedはReservation変更の事実を表現する。
- Reservationの現在状態をEvent Payloadに記録する。
- CreatedByとUpdatedByを分離する。
- CreatedAtとUpdatedAtを分離する。
- Check In前にReservation内容を確定する。
- GuestCountとReservationSeatの整合性を維持する。
- 座席指定公演では人数変更による非連席の可能性を事前告知する。
- ReservationSeatは予約情報として管理し、個別にCheck Inしない。
- Check InはReservation単位で行う。
- CHECKED_INのReservationは変更しない。
- ReservationUpdatedではHistoryを管理しない。
- Reservation DomainはHistory Domainへ直接依存しない。
- Event HandlerをPublisherから分離する。
- Event処理は冪等性を確保する。
- Event PayloadはEvent発生時点の事実を表現する。
