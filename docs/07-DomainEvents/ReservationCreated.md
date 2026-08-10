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