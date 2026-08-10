# StageArt Blueprint
# Domain Event : ReservationCheckedIn

Version : 1.0

---

# Purpose

ReservationCheckedInは、
ReservationがCheck Inされたことを表すDomain Eventである。

Reservation DomainがCheck Inを確定した後に発行する。

ReservationCheckedInは、
「予約者が実際に来場した」という事実を表現する。

ReservationCheckedIn自身はHistoryを直接操作しない。

History DomainはReservationCheckedInを受信し、
Audience Historyを生成する。

---

# Event

ReservationCheckedIn

---

# Publisher

Reservation

Reservation DomainがEventを発行する。

---

# Trigger

以下のBusiness Actionによって発生する。

Reservation
    │
    │ Check In
    ▼
ReservationCheckedIn

ReservationのCheck Inが正常に完了した場合に発行する。

---

# Event Payload

{
  "eventId": "event-004",
  "eventType": "ReservationCheckedIn",
  "occurredAt": "2026-08-10T09:00:00+09:00",
  "reservationId": "reservation-001",
  "performanceId": "performance-001",
  "productionId": "production-001",
  "bookerId": "person-001"
}

---

# Payload Definitions

| Field | Description |
|---|---|
| eventId | Eventを一意に識別するID |
| eventType | Event Type |
| occurredAt | Eventが発生した日時 |
| reservationId | Check InされたReservation |
| performanceId | 観覧対象となったPerformance |
| productionId | 観覧対象となったProduction |
| bookerId | 予約者であるPerson |

---

# Event Flow

Reservation
    │
    │ Check In
    ▼
ReservationCheckedIn
    │
    ▼
History Domain
    │
    ▼
Audience History

ReservationCheckedInを受信したHistory Domainは、
Audience Historyを生成する。

---

# History Generation

生成するHistoryは以下の内容とする。

HistoryType
    = AUDIENCE

Subject
    = Reservation.Booker

ParticipantType
    = NULL

Production
    = Reservation.Performance.Production

Performance
    = Reservation.Performance

EventDateTime
    = ReservationCheckedIn.occurredAt

---

# Subject

Audience HistoryのSubjectには、
ReservationのBookerを使用する。

ReservationCheckedInでは、

bookerId
    ↓
Person
    ↓
Subject

という関係で活動主体を識別する。

Audience Historyは予約者本人の観劇履歴として記録する。

---

# HandledParticipant

ReservationにHandledParticipantが設定されている場合でも、
Audience HistoryのSubjectにはHandledParticipantを使用しない。

HandledParticipantは、
予約における「○○扱い」を表す情報である。

観客として来場した主体はBookerであるため、

HandledParticipant
    ↓
Audience History

という関係は作らない。

---

# Production

ReservationCheckedInはProductionIdをEvent Payloadに含める。

History DomainはProductionIdを使用して、
Audience HistoryのProductionを決定する。

Productionは必須である。

---

# Performance

ReservationCheckedInはPerformanceIdをEvent Payloadに含める。

History DomainはPerformanceIdを使用して、
Audience HistoryのPerformanceを決定する。

Audience HistoryではPerformanceを必須とする。

---

# Participant Type

Audience HistoryではParticipantTypeを使用しない。

ParticipantType
    = NULL

AudienceはProductionへの参加区分ではないため、
ParticipantTypeを設定しない。

---

# Event Handler

History DomainはReservationCheckedInを受信する。

ReservationCheckedIn
        ↓
AudienceHistoryHandler
        ↓
History生成

Event HandlerはReservation Domainの内部実装を
直接操作しない。

---

# Idempotency

ReservationCheckedInの処理は冪等であること。

同じeventIdを複数回受信した場合でも、
同一のAudience Historyを複数生成しない。

ReservationCheckedIn
eventId = event-004

1回目
    ↓
Audience History生成

2回目
    ↓
処理済み
    ↓
History生成しない

---

# Failure Handling

History生成に失敗した場合でも、
ReservationのCheck In自体を取り消さない。

ReservationCheckedInは発行済みEventとして保持し、
Event Handlerが再実行できる状態とする。

---

# Business Rules

- ReservationCheckedInはReservation Domainが発行する。
- ReservationCheckedInはCheck In完了後に発行する。
- ReservationCheckedInはHistoryを直接操作しない。
- History DomainはReservationCheckedInを契機としてAudience Historyを生成する。
- HistoryTypeはAUDIENCEとする。
- SubjectはReservationのBookerを使用する。
- HandledParticipantはAudience HistoryのSubjectにならない。
- ParticipantTypeはNULLとする。
- ProductionはReservationのPerformanceが所属するProductionを使用する。
- PerformanceはReservationのPerformanceを使用する。
- Performanceは必須である。
- EventDateTimeはCheck Inの発生日時を使用する。
- Event処理は冪等である。
- Event Handlerの失敗によってCheck Inを取り消さない。

---

# Design Principles

- Eventは発生したBusiness Eventを表現する。
- Event PublisherとEvent Handlerを分離する。
- Reservation DomainはHistory Domainへ直接依存しない。
- History DomainはReservation Domainへ直接依存しない。
- Audience Historyは実際の来場を表現する。
- ReservationCreatedだけではAudience Historyを生成しない。
- ReservationCheckedInによってAudience Historyを生成する。
- HandledParticipantとAudienceのSubjectを混同しない。
- Audience HistoryではParticipantTypeを使用しない。
- 同一Eventの重複処理を防止する。
- HistoryはDomain Eventを契機として生成する。