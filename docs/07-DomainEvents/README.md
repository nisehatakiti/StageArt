# StageArt Blueprint
# 07 - Domain Events

Version : 2.0

---

# Purpose

Domain Eventは、Domain内で発生した重要なBusiness Eventを表す。

Domain Eventは「何が起きたか」を表現し、
そのEventを契機として別のBusiness Processを開始する。

Domain EventはDomain間の直接依存を減らし、
Business Processを疎結合に連携するために利用する。

---

# Basic Concept

Domain Eventは以下の形式で発生する。

```text
Domain
   │
   │ Business Action
   ▼
Domain Event
   │
   ├── Event Handler
   ├── History
   ├── Notification
   └── Other Business Process
```

Eventを発行したDomainは、
Eventを受け取ったDomainの処理内容を直接管理しない。

---

# Domain Event Principles

Domain Eventには以下の原則を適用する。

- 過去に発生したBusiness Eventを表現する。
- Event名は過去形で表現する。
- Eventは発生元Domainによって発行される。
- Eventを受け取るDomainは発行元Domainへ直接依存しない。
- Event HandlerはEventを契機としてBusiness Processを実行する。
- 同じEventを複数回処理しても結果が重複しないよう冪等性を確保する。
- Eventの処理に失敗した場合でも、元のTransactionとの責務を混同しない。

---

# Current Domain Events

現在StageArtで定義する主要なDomain Eventは以下。

## Organization

```text
OrganizationCreated
```

---

## Production

```text
ProductionCreated
ProductionPublished
ProductionArchived
```

---

## Participant

```text
ParticipantAdded
ParticipantUpdated
ParticipantRemoved
```

---

## Reservation

```text
ReservationCreated
ReservationUpdated
ReservationCheckedIn
ReservationCancelled
```

---

# Event Flow

## Organization Created

```text
Organization
    │
    │ Create
    ▼
OrganizationCreated
```

Organizationの作成を契機として、
Organizationに関連する初期Business Processを開始する。

---

## Production Created

```text
Production
    │
    │ Create
    ▼
ProductionCreated
```

Productionの作成を契機として、
Productionに関連する初期Business Processを開始する。

---

## Production Published

```text
Production
    │
    │ Publish
    ▼
ProductionPublished
```

Productionが公開状態になったことを表す。

公開を契機として、
検索公開、通知などのBusiness Processを開始できる。

---

# Participant Event Flow

## Participant Added

```text
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
History
HistoryType = PARTICIPATION
ParticipantType = Participant.ParticipantType
```

ParticipantがProductionへ追加されたことを契機として、
Participation Historyを生成する。

HistoryはParticipantが直接生成するのではなく、
History DomainがEventを受け取って生成する。

---

## Participant Updated

```text
Participant
    │
    │ Update
    ▼
ParticipantUpdated
    │
    ▼
History Domain
```

Participantの変更内容に応じて、
既存のParticipation Historyを更新する。

ParticipantTypeが変更された場合は、
HistoryのParticipantTypeにも反映する。

Role、CreditOrder、Visibilityなど、
Historyの活動内容に影響しない項目の変更では
Historyを更新しない。

---

## Participant Removed

```text
Participant
    │
    │ Remove
    ▼
ParticipantRemoved
    │
    ▼
History Domain
```

Participantが削除されても、
過去のParticipation Historyは削除しない。

過去に発生した活動実績は、
過去の事実として保持する。

---

# Reservation Event Flow

## Reservation Created

```text
Reservation
    │
    │ Create
    ▼
ReservationCreated
```

Reservationが作成されたことを表す。

ReservationCreatedだけでは
Audience Historyを生成しない。

予約したことと、
実際に観劇したことは別のBusiness Eventとして扱う。

---

## Reservation Updated

```text
Reservation
    │
    │ Update
    ▼
ReservationUpdated
```

Reservationの内容が変更されたことを表す。

HandledParticipantの変更も
ReservationUpdatedとして扱う。

HandledParticipantが変更されても、
それだけではHistoryを生成・更新しない。

---

## Reservation Checked In

```text
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
History
HistoryType = AUDIENCE
```

ReservationのCheck Inを契機として、
Audience Historyを生成する。

Audience HistoryにはParticipantTypeを設定しない。

---

## Reservation Cancelled

```text
Reservation
    │
    │ Cancel
    ▼
ReservationCancelled
```

Reservationがキャンセルされたことを表す。

ReservationCancelledでは
Audience Historyを生成しない。

既にCheck In済みの場合のHistoryについては、
別途定義されたBusiness Ruleに従う。

---

# History Integration

Historyは独立したDomainである。

ParticipantやReservationはHistoryを直接管理しない。

HistoryはDomain Eventを契機として生成・更新される。

```text
ParticipantAdded
        │
        ▼
Participation History
```

```text
ReservationCheckedIn
        │
        ▼
Audience History
```

---

# History Generation Rules

## Participation History

以下のEventを契機として生成・更新する。

```text
ParticipantAdded
ParticipantUpdated
ParticipantRemoved
```

HistoryType

```text
PARTICIPATION
```

ParticipantType

```text
Participant.ParticipantType
```

ParticipantRemovedによって
過去のHistoryを削除しない。

---

## Audience History

以下のEventを契機として生成する。

```text
ReservationCheckedIn
```

HistoryType

```text
AUDIENCE
```

ParticipantType

```text
NULL
```

以下のEventではAudience Historyを生成しない。

```text
ReservationCreated
ReservationUpdated
ReservationCancelled
```

---

# HandledParticipant

HandledParticipantはReservationにおける
「○○扱い」のParticipantを表す。

```text
Reservation
     │
     ▼
HandledParticipant
     │
     ▼
Participant
```

HandledParticipantの指定・変更は
ReservationのBusiness Eventとして扱う。

ただし、

```text
HandledParticipant
        ↓
History
```

という直接的なHistory生成は行わない。

HandledParticipantは
予約の関連付けを表す情報であり、
それ自体が活動実績を意味しない。

---

# Event Handler

Event HandlerはDomain Eventを受け取り、
必要なBusiness Processを実行する。

例）

```text
ParticipantAdded
        ↓
ParticipationHistoryHandler
        ↓
History生成
```

```text
ReservationCheckedIn
        ↓
AudienceHistoryHandler
        ↓
History生成
```

Event Handlerは発行元Domainの内部実装を直接操作しない。

---

# Idempotency

Domain Eventの処理は冪等であることを原則とする。

同じEventが複数回配送された場合でも、
HistoryなどのBusiness Dataが重複して生成されないようにする。

例えば、

```text
ParticipantAdded
EventId = xxx
```

を複数回受信しても、
同じParticipation Historyを複数作成しない。

---

# Transaction Boundary

Domain Eventの発行元Domainと、
Event Handlerによる後続処理は、
必ずしも同一Transactionには含めない。

```text
Transaction A

Participant
    ↓
ParticipantAdded
```

```text
Transaction B

ParticipantAdded
    ↓
History
```

このようにDomain Eventを介して
非同期に処理できる構造とする。

---

# Event Payload

Domain Eventには、
後続処理に必要な最小限の情報を含める。

例）

```json
{
  "eventId": "event-001",
  "eventType": "ParticipantAdded",
  "occurredAt": "2026-08-10T09:00:00+09:00",
  "participantId": "participant-001",
  "productionId": "production-001",
  "subjectId": "person-001",
  "participantType": "CAST"
}
```

Event PayloadはEvent発生時点の事実を表す。

後続処理で必要な情報を、
発行元Domainへ都度問い合わせることを原則としない。

---

# Event Naming

Event名は過去形で表現する。

```text
OrganizationCreated
ProductionCreated
ProductionPublished
ParticipantAdded
ParticipantUpdated
ParticipantRemoved
ReservationCreated
ReservationUpdated
ReservationCheckedIn
ReservationCancelled
```

Commandではなく、
「すでに発生した事実」を表現する。

---

# Event Ownership

Event | Publisher
--- | ---
OrganizationCreated | Organization
ProductionCreated | Production
ProductionPublished | Production
ProductionArchived | Production
ParticipantAdded | Participant
ParticipantUpdated | Participant
ParticipantRemoved | Participant
ReservationCreated | Reservation
ReservationUpdated | Reservation
ReservationCheckedIn | Reservation
ReservationCancelled | Reservation

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
- 過去のParticipation HistoryはParticipantRemovedによって削除しない。
- Event処理は冪等性を確保する。
- Event Payloadは発生時点の事実を表現する。