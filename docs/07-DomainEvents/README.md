# StageArt Blueprint
# 07 - Domain Events

Version : 2.0

---

# Purpose

Domain Eventは、Domain内で発生した重要なBusiness Eventを表す。

Domain Eventは「何が起きたか」という事実を表現し、
そのEventを契機として別のBusiness Processを開始する。

Domain Eventを利用することで、
Domain間の直接依存を減らし、
Business Processを疎結合に連携する。

---

# Concept

Domain Eventは、発生元DomainがBusiness Actionを完了したことを表す。

Domain
    │
    │ Business Action
    ▼
Domain Event
    │
    ▼
Event Handler
    │
    ▼
Business Process

Eventを発行したDomainは、
Eventを受け取ったDomainの処理内容を直接管理しない。

---

# Domain Event Principles

Domain Eventには以下の原則を適用する。

- 過去に発生したBusiness Eventを表現する。
- Event名は過去形で表現する。
- Eventは発生元Domainによって発行する。
- Eventを受け取るDomainは発行元Domainへ直接依存しない。
- Event HandlerはEventを契機としてBusiness Processを実行する。
- Event処理は冪等性を確保する。
- Event PayloadはEvent発生時点の事実を表現する。
- Event Handlerの失敗によって、発生元Domainの処理結果を取り消さない。

---

# Current Domain Events

現在StageArtで定義する主要なDomain Eventは以下。

## Organization

OrganizationCreated

---

## Production

ProductionCreated
ProductionPublished
ProductionArchived

---

## Participant

ParticipantAdded
ParticipantUpdated
ParticipantRemoved

---

## Reservation

ReservationCreated
ReservationUpdated
ReservationCheckedIn
ReservationCancelled

---

# Event Flow

## Organization Created

Organization
    │
    │ Create
    ▼
OrganizationCreated

Organizationが作成された事実を表す。

OrganizationCreatedを契機として、
Organizationに関連する後続のBusiness Processを開始できる。

---

## Production Created

Production
    │
    │ Create
    ▼
ProductionCreated

Productionが作成された事実を表す。

ProductionCreatedを契機として、
Productionに関連する後続のBusiness Processを開始できる。

---

## Production Published

Production
    │
    │ Publish
    ▼
ProductionPublished

Productionが公開状態になった事実を表す。

公開を契機として、

- 検索公開
- 通知
- その他の公開関連処理

などのBusiness Processを開始できる。

---

## Production Archived

Production
    │
    │ Archive
    ▼
ProductionArchived

Productionがアーカイブされた事実を表す。

---

# Participant Event Flow

## Participant Added

Participant
    │
    │ Add
    ▼
ParticipantAdded
    │
    ▼
History Domain
    │
    ▼
Participation History

ParticipantがProductionへ追加された事実を表す。

History DomainはParticipantAddedを契機として
Participation Historyを生成する。

生成されるHistoryは、

HistoryType
    = PARTICIPATION

Subject
    = Participant.Subject

ParticipantType
    = Participant.ParticipantType

Production
    = Participant.Production

とする。

ParticipantはHistoryを直接生成・更新しない。

---

## Participant Updated

Participant
    │
    │ Update
    ▼
ParticipantUpdated
    │
    ▼
History Domain

Participantが更新された事実を表す。

History Domainは変更内容に応じて
既存のParticipation Historyを更新する。

ParticipantTypeが変更された場合は、
HistoryのParticipantTypeへ反映する。

Role、CreditOrder、Visibilityなど、
活動実績そのものに影響しない情報の変更では
Historyを更新しない。

---

## Participant Removed

Participant
    │
    │ Remove
    ▼
ParticipantRemoved
    │
    ▼
History Domain

Participantが削除された事実を表す。

Participantが削除されても、
過去のParticipation Historyは削除しない。

過去に発生した活動実績は、
過去の事実として保持する。

---

# Reservation Event Flow

## Reservation Created

Reservation
    │
    │ Create
    ▼
ReservationCreated

Reservationが作成された事実を表す。

ReservationCreatedだけでは
Audience Historyを生成しない。

予約した事実と、
実際に観劇した事実は別のBusiness Eventとして扱う。

---

## Reservation Updated

Reservation
    │
    │ Update
    ▼
ReservationUpdated

Reservationが更新された事実を表す。

以下の変更を含む。

- HandledParticipant
- Companion
- ReservationSeat
- TicketType
- その他Reservationの管理項目

HandledParticipantが変更されても、
それだけではHistoryを生成・更新しない。

HandledParticipantは予約上の「扱い」を表す情報であり、
それ自体が活動実績を意味しない。

---

## Reservation Checked In

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

ReservationがCheck Inされた事実を表す。

History DomainはReservationCheckedInを契機として
Audience Historyを生成する。

生成されるHistoryは、

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

とする。

---

## Reservation Cancelled

Reservation
    │
    │ Cancel
    ▼
ReservationCancelled

Reservationがキャンセルされた事実を表す。

ReservationCancelledでは
Audience Historyを生成しない。

---

# History Integration

Historyは独立したDomainである。

ParticipantやReservationはHistoryを直接管理しない。

History DomainはDomain Eventを受け取り、
必要なHistoryを生成・更新する。

ParticipantAdded
        │
        ▼
Participation History

ReservationCheckedIn
        │
        ▼
Audience History

---

# History Generation Rules

## Participation History

Participantに関する以下のEventを契機とする。

ParticipantAdded
ParticipantUpdated
ParticipantRemoved

HistoryType

PARTICIPATION

Subject

Participant.Subject

ParticipantType

Participant.ParticipantType

ParticipantRemovedによって
過去のHistoryを削除しない。

---

## Audience History

以下のEventを契機として生成する。

ReservationCheckedIn

HistoryType

AUDIENCE

Subject

Reservation.Booker

ParticipantType

NULL

以下のEventではAudience Historyを生成しない。

ReservationCreated
ReservationUpdated
ReservationCancelled

---

# HandledParticipant

HandledParticipantはReservationにおける
「○○扱い」のParticipantを表す。

Reservation
     │
     ▼
HandledParticipant
     │
     ▼
Participant

HandledParticipantの指定・変更は
ReservationのBusiness Eventとして扱う。

ただし、

HandledParticipant
        ↓
History

という直接的なHistory生成は行わない。

HandledParticipantは予約における関連付けを表す情報であり、
それ自体がParticipantの活動実績を意味しない。

---

# Event Handler

Event HandlerはDomain Eventを受け取り、
必要なBusiness Processを実行する。

例）

ParticipantAdded
        ↓
ParticipationHistoryHandler
        ↓
History生成

ReservationCheckedIn
        ↓
AudienceHistoryHandler
        ↓
History生成

Event Handlerは発行元Domainの内部実装を
直接操作しない。

---

# Idempotency

Domain Eventの処理は冪等であることを原則とする。

同じEventが複数回配送された場合でも、
HistoryなどのBusiness Dataが重複して生成されないようにする。

例）

ParticipantAdded
EventId = event-001

1回目
    ↓
History生成

2回目
    ↓
処理済み
    ↓
History生成しない

---

# Transaction Boundary

Domain Eventの発行元Domainと、
Event Handlerによる後続処理は、
必ずしも同一Transactionには含めない。

Transaction A

Participant
    ↓
ParticipantAdded

Transaction B

ParticipantAdded
    ↓
History

このようにDomain Eventを介して
非同期に処理できる構造とする。

---

# Event Payload

Domain Eventには、
後続処理に必要な最小限の情報を含める。

例）

{
  "eventId": "event-001",
  "eventType": "ParticipantAdded",
  "occurredAt": "2026-08-10T09:00:00+09:00",
  "participantId": "participant-001",
  "productionId": "production-001",
  "subjectId": "person-001",
  "participantType": "CAST"
}

Event PayloadはEvent発生時点の事実を表す。

Event Handlerは、可能な限りEvent Payloadだけで
後続処理を実行できるようにする。

---

# Event Naming

Event名は過去形で表現する。

OrganizationCreated
ProductionCreated
ProductionPublished
ProductionArchived
ParticipantAdded
ParticipantUpdated
ParticipantRemoved
ReservationCreated
ReservationUpdated
ReservationCheckedIn
ReservationCancelled

Commandではなく、
「すでに発生した事実」を表現する。

---

# Event Ownership

| Event | Publisher |
|---|---|
| OrganizationCreated | Organization |
| ProductionCreated | Production |
| ProductionPublished | Production |
| ProductionArchived | Production |
| ParticipantAdded | Participant |
| ParticipantUpdated | Participant |
| ParticipantRemoved | Participant |
| ReservationCreated | Reservation |
| ReservationUpdated | Reservation |
| ReservationCheckedIn | Reservation |
| ReservationCancelled | Reservation |

---

# Future Event Consumers

Domain Eventは現在のBusiness Processだけに限定されない。

将来的に以下のようなConsumerを追加できる。

Domain Event
    │
    ├── History
    ├── Notification
    ├── Search Index
    ├── Analytics
    └── Other Business Process

Event PublisherはConsumerの存在を意識しない。

---

# Design Principles

- Domain Eventは過去に発生したBusiness Eventを表現する。
- Event名は過去形で表現する。
- Eventは発生元Domainが発行する。
- Event Handlerは後続のBusiness Processを実行する。
- Domain間の直接依存を避ける。
- HistoryはDomain Eventを契機として生成・更新する。
- ParticipantはHistoryを直接管理しない。
- ReservationはHistoryを直接管理しない。
- HandledParticipantはHistoryを生成する理由にならない。
- ReservationCreatedではAudience Historyを生成しない。
- ReservationCheckedInでAudience Historyを生成する。
- ParticipantRemovedによって過去のParticipation Historyを削除しない。
- Event処理は冪等性を確保する。
- Event Payloadは発生時点の事実を表現する。
- Event PublisherはEvent Consumerを意識しない。