# StageArt Blueprint
# Domain Event : ReservationCreated

Version : 1.0

---

# Purpose

ReservationCreatedは、
Performanceに対するReservationが作成されたことを表すDomain Eventである。

Reservation DomainがReservationの作成を確定した後に発行する。

ReservationCreatedは、
「予約が作成された」という過去に発生したBusiness Eventを表現する。

ReservationCreated自身はHistoryを直接操作しない。

予約を作成したことと、
実際に観劇したことは別のBusiness Eventとして扱う。

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
  "performanceId": "performance-001",
  "productionId": "production-001",
  "bookerId": "person-001",
  "handledParticipantId": "participant-001"
}

---

# Payload Definitions

| Field | Description |
|---|---|
| eventId | Eventを一意に識別するID |
| eventType | Event Type |
| occurredAt | Eventが発生した日時 |
| reservationId | 作成されたReservation |
| performanceId | 予約対象となったPerformance |
| productionId | 予約対象となったProduction |
| bookerId | 予約者であるPerson |
| handledParticipantId | 予約を担当するParticipant。未指定の場合はNULL |

---

# Event Flow

Reservation
    │
    │ Create
    ▼
ReservationCreated

ReservationCreatedを契機として、
Reservationに関連する後続のBusiness Processを開始できる。

ただし、Audience Historyは生成しない。

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

観劇履歴は、

ReservationCheckedIn
    ↓
Audience History

によって生成する。

---

# HandledParticipant

ReservationCreatedには、
Reservationに設定されたHandledParticipantを含めることができる。

HandledParticipantは、
予約における「○○扱い」のParticipantを表す。

HandledParticipantが設定されていない場合はNULLとする。

HandledParticipantは予約の関連情報であり、
Participantの活動実績を意味しない。

そのため、

ReservationCreated
    ↓
HandledParticipant
    ↓
History

というHistory生成は行わない。

---

# Booker

ReservationCreatedはBookerをEvent Payloadに含める。

Bookerは予約者であるPersonを表す。

BookerはReservationに必須である。

ReservationCreatedでは、
Bookerの観劇を意味しない。

観劇の事実はReservationCheckedInによって確定する。

---

# Production

ReservationCreatedはProductionIdをEvent Payloadに含める。

ProductionIdは、
予約対象となったPerformanceが所属するProductionを表す。

---

# Performance

ReservationCreatedはPerformanceIdをEvent Payloadに含める。

PerformanceIdは、
予約対象となったPerformanceを表す。

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
Search / Analytics

Event HandlerはReservation Domainの内部実装を
直接操作しない。

---

# Idempotency

ReservationCreatedの処理は冪等であること。

同じeventIdを複数回受信した場合でも、
後続Business Processを重複して実行しない。

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
- ReservationCreatedはHistoryを直接操作しない。
- ReservationCreatedではAudience Historyを生成しない。
- Bookerは予約者であるPersonを表す。
- Bookerは必須である。
- HandledParticipantは任意である。
- HandledParticipantがない場合はNULLとする。
- HandledParticipantはHistoryを生成する理由にならない。
- Productionは予約対象となったPerformanceのProductionを表す。
- Performanceは予約対象となったPerformanceを表す。
- Event処理は冪等である。
- Event Handlerの失敗によってReservation作成を取り消さない。

---

# Design Principles

- Eventは発生したBusiness Eventを表現する。
- Event PublisherとEvent Handlerを分離する。
- Reservation Domainは後続Business Processへ直接依存しない。
- Reservation DomainはHistory Domainへ直接依存しない。
- 予約した事実と観劇した事実を分離する。
- ReservationCreatedではAudience Historyを生成しない。
- ReservationCheckedInでAudience Historyを生成する。
- HandledParticipantは予約の「扱い」を表す。
- HandledParticipantはHistoryの活動主体ではない。
- 同一Eventの重複処理を防止する。
- Event Payloadには後続処理に必要な情報を含める。