# StageArt Blueprint
# Domain Event : ParticipantRemoved

Version : 1.0

---

# Purpose

ParticipantRemovedは、
ProductionからParticipantが削除されたことを表すDomain Eventである。

Participant DomainがParticipantの削除を確定した後に発行する。

ParticipantRemovedは、
「Participantが削除された」という過去に発生した
Business Eventを表現する。

ParticipantRemoved自身はHistoryを直接操作しない。

History DomainはParticipantRemovedを受信するが、
過去のParticipation Historyは削除しない。

---

# Event

ParticipantRemoved

---

# Publisher

Participant

Participant DomainがEventを発行する。

---

# Trigger

以下のBusiness Actionによって発生する。

Participant
    │
    │ Remove
    ▼
ParticipantRemoved

Participantの削除が正常に完了した場合に発行する。

---

# Event Payload

{
  "eventId": "event-003",
  "eventType": "ParticipantRemoved",
  "occurredAt": "2026-08-10T09:00:00+09:00",
  "participantId": "participant-001",
  "productionId": "production-001",
  "subjectId": "person-001",
  "participantType": "CAST"
}

---

# Payload Definitions

| Field | Description |
|---|---|
| eventId | Eventを一意に識別するID |
| eventType | Event Type |
| occurredAt | Eventが発生した日時 |
| participantId | 削除されたParticipant |
| productionId | Participantが所属していたProduction |
| subjectId | Participantが参照していたSubject |
| participantType | 削除時点のParticipantの参加区分 |

---

# Event Flow

Participant
    │
    │ Remove
    ▼
ParticipantRemoved
    │
    ▼
History Domain
    │
    ▼
既存Historyを保持

ParticipantRemovedを受信しても、
過去のParticipation Historyは削除しない。

---

# History Handling

ParticipantRemovedによって、
既存のParticipation Historyを削除しない。

Participantが削除されたことは、
現在のParticipant状態が変更されたことを意味する。

一方、Historyは過去に発生した活動実績を表す。

そのため、

ParticipantRemoved
    ↓
過去のHistoryを削除しない

とする。

---

# Historical Record

例えば、

Participant
    Subject = Person A
    Production = Production X
    ParticipantType = CAST

として登録されていた場合、

その後Participantが削除されても、

History
    Subject = Person A
    Production = Production X
    HistoryType = PARTICIPATION
    ParticipantType = CAST

は保持する。

これにより、
過去にProductionへ参加したという事実を維持する。

---

# Subject

ParticipantRemovedはSubjectIdをEvent Payloadに含める。

History DomainはSubjectIdを使用して、
既存のParticipation Historyを識別する。

Subjectは以下のいずれかを表す。

PERSON
ORGANIZATION

Participantが削除された後も、
Historyが参照するSubjectは変更しない。

---

# Participant Type

ParticipantRemovedは削除時点のParticipantTypeを
Event Payloadに含める。

ParticipantTypeは過去のParticipation Historyを
識別するための情報として利用できる。

例）

CAST
STAFF
DIRECTOR
PRODUCER
ORGANIZER
SPONSOR
SUPPORTER

ParticipantRemovedによって、
既存HistoryのParticipantTypeを変更しない。

---

# Production

ParticipantRemovedはProductionIdをEvent Payloadに含める。

ProductionIdは、
削除されたParticipantが所属していたProductionを表す。

既存HistoryはProductionとの関連を維持する。

---

# Performance

ParticipantRemovedによって、
Participation HistoryのPerformanceは変更しない。

Participation Historyでは、

Performance = NULL

とする。

---

# Event Handler

History DomainはParticipantRemovedを受信する。

ParticipantRemoved
        ↓
ParticipationHistoryHandler
        ↓
過去のHistoryを保持

Event HandlerはParticipant Domainの内部実装を
直接操作しない。

---

# Idempotency

ParticipantRemovedの処理は冪等であること。

同じeventIdを複数回受信した場合でも、
Historyを重複して変更・削除しない。

ParticipantRemoved
eventId = event-003

1回目
    ↓
Historyを保持

2回目
    ↓
処理済み
    ↓
Historyを保持

---

# Failure Handling

History Domain側の処理に失敗した場合でも、
Participantの削除自体を取り消さない。

ParticipantRemovedは発行済みEventとして保持し、
Event Handlerが再実行できる状態とする。

---

# Business Rules

- ParticipantRemovedはParticipant Domainが発行する。
- ParticipantRemovedはParticipant削除完了後に発行する。
- ParticipantRemovedはHistoryを直接操作しない。
- ParticipantRemovedによって過去のParticipation Historyを削除しない。
- 過去の活動実績は保持する。
- Subjectは削除時点のParticipantが参照していたSubjectを使用する。
- Productionは削除時点のParticipantが所属していたProductionを使用する。
- ParticipantTypeは削除時点の値をEvent Payloadに含める。
- 既存HistoryのParticipantTypeは変更しない。
- PerformanceはNULLとする。
- Event処理は冪等である。
- Event Handlerの失敗によってParticipant削除を取り消さない。

---

# Design Principles

- Eventは発生したBusiness Eventを表現する。
- Event PublisherとEvent Handlerを分離する。
- Participant DomainはHistory Domainへ直接依存しない。
- History DomainはParticipant Domainへ直接依存しない。
- 過去の活動実績と現在のParticipant状態を分離する。
- ParticipantRemovedによって過去のHistoryを削除しない。
- Event Payloadには削除時点の事実を含める。
- 同一Eventの重複処理を防止する。
- Historyは過去に発生した事実として保持する。