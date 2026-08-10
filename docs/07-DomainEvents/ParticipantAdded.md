# StageArt Blueprint
# Domain Event : ParticipantAdded

Version : 1.0

---

# Purpose

ParticipantAddedは、
ProductionへParticipantが追加されたことを表すDomain Eventである。

Participant DomainがParticipantの追加を確定した後に発行する。

ParticipantAddedは「Participantが追加された」という
過去に発生したBusiness Eventを表現する。

ParticipantAdded自身はHistoryを直接操作しない。

History DomainはParticipantAddedを契機として、
Participation Historyを生成する。

---

# Event

ParticipantAdded

---

# Publisher

Participant

Participant DomainがEventを発行する。

---

# Trigger

以下のBusiness Actionによって発生する。

Production
    │
    │ Participantを追加
    ▼
Participant
    │
    │ Add completed
    ▼
ParticipantAdded

Participantの追加が正常に完了した場合に発行する。

---

# Event Payload

{
  "eventId": "event-001",
  "eventType": "ParticipantAdded",
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
| participantId | 追加されたParticipant |
| productionId | Participantが所属するProduction |
| subjectId | Participantが参照するSubject |
| participantType | Participantの参加区分 |

---

# Event Flow

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

ParticipantAddedを受信したHistory Domainは、
Participation Historyを生成する。

---

# History Generation

生成するHistoryは以下の内容とする。

HistoryType
    = PARTICIPATION

Subject
    = Participant.Subject

ParticipantType
    = Participant.ParticipantType

Production
    = Participant.Production

Performance
    = NULL

ParticipantはProduction単位で登録されるため、
ParticipantAddedによって生成されるParticipation Historyには
Performanceを設定しない。

---

# Subject

ParticipantAddedはSubjectIdをEvent Payloadに含める。

History DomainはSubjectIdを使用して、
HistoryのSubjectを決定する。

Subjectは以下のいずれかを表す。

PERSON
ORGANIZATION

Participant DomainはPersonまたはOrganizationを
直接History Domainへ渡さない。

Subjectを通じて活動主体を識別する。

---

# Participant Type

ParticipantAddedはParticipantTypeをEvent Payloadに含める。

ParticipantTypeはParticipation Historyの
参加区分として使用する。

例）

CAST
STAFF
DIRECTOR
PRODUCER
ORGANIZER
SPONSOR
SUPPORTER

---

# Production

ParticipantAddedはProductionIdをEvent Payloadに含める。

History DomainはProductionIdを使用して、
Participation HistoryのProductionを決定する。

Productionは必須である。

---

# Performance

ParticipantAddedではPerformanceを指定しない。

Performance = NULL

ParticipantはProductionへの参加を表すため、
特定のPerformanceへの参加とは区別する。

---

# Event Handler

History DomainはParticipantAddedを受信する。

ParticipantAdded
        ↓
ParticipationHistoryHandler
        ↓
History生成

Event HandlerはParticipant Domainの内部実装を
直接操作しない。

---

# Idempotency

ParticipantAddedの処理は冪等であること。

同じeventIdを複数回受信した場合でも、
同一のParticipation Historyを複数生成しない。

ParticipantAdded
eventId = event-001

1回目
    ↓
History生成

2回目
    ↓
処理済み
    ↓
History生成しない

---

# Failure Handling

History生成に失敗した場合でも、
Participantの追加自体を取り消さない。

ParticipantAddedは発行済みEventとして保持し、
Event Handlerが再実行できる状態とする。

---

# Business Rules

- ParticipantAddedはParticipant Domainが発行する。
- ParticipantAddedはParticipant追加完了後に発行する。
- ParticipantAddedはPersonまたはOrganizationの作成を意味しない。
- ParticipantAddedはHistoryを直接操作しない。
- History DomainはParticipantAddedを契機としてParticipation Historyを生成する。
- HistoryTypeはPARTICIPATIONとする。
- SubjectはParticipantのSubjectを使用する。
- ParticipantTypeはParticipantのParticipantTypeを使用する。
- ProductionはParticipantのProductionを使用する。
- PerformanceはNULLとする。
- Event処理は冪等である。
- Event Handlerの失敗によってParticipant追加を取り消さない。

---

# Design Principles

- Eventは発生したBusiness Eventを表現する。
- Event PublisherとEvent Handlerを分離する。
- Participant DomainはHistory Domainへ直接依存しない。
- History DomainはParticipant Domainへ直接依存しない。
- Event Payloadには後続処理に必要な情報を含める。
- 同一Eventの重複処理を防止する。
- HistoryはDomain Eventを契機として生成する。