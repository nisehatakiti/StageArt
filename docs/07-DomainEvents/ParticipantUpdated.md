# StageArt Blueprint
# Domain Event : ParticipantUpdated

Version : 1.0

---

# Purpose

ParticipantUpdatedは、
Productionに登録されているParticipantが更新されたことを表すDomain Eventである。

Participant DomainがParticipantの更新を確定した後に発行する。

ParticipantUpdatedは、Participantに変更が発生したという
過去に発生したBusiness Eventを表現する。

ParticipantUpdated自身はHistoryを直接操作しない。

History DomainはParticipantUpdatedを受信し、
変更内容に応じてParticipation Historyを更新する。

---

# Event

ParticipantUpdated

---

# Publisher

Participant

Participant DomainがEventを発行する。

---

# Trigger

以下のBusiness Actionによって発生する。

Participant
    │
    │ Update
    ▼
ParticipantUpdated

Participantの更新が正常に完了した場合に発行する。

---

# Event Payload

{
  "eventId": "event-002",
  "eventType": "ParticipantUpdated",
  "occurredAt": "2026-08-10T09:00:00+09:00",
  "participantId": "participant-001",
  "productionId": "production-001",
  "subjectId": "person-001",
  "participantType": "CAST",
  "changedFields": [
    "participantType"
  ]
}

---

# Payload Definitions

| Field | Description |
|---|---|
| eventId | Eventを一意に識別するID |
| eventType | Event Type |
| occurredAt | Eventが発生した日時 |
| participantId | 更新されたParticipant |
| productionId | Participantが所属するProduction |
| subjectId | Participantが参照するSubject |
| participantType | 更新後のParticipantの参加区分 |
| changedFields | 変更された項目 |

---

# Event Flow

Participant
    │
    │ Update
    ▼
ParticipantUpdated
    │
    ▼
History Domain
    │
    ▼
Participation History

ParticipantUpdatedを受信したHistory Domainは、
変更内容を確認し、必要な場合のみParticipation Historyを更新する。

---

# History Update Rules

ParticipantUpdatedによって、
すべての変更がHistoryへ反映されるわけではない。

Historyの活動実績に影響する変更のみを反映する。

---

## ParticipantType Changed

ParticipantTypeが変更された場合、
Participation HistoryのParticipantTypeを更新する。

例）

CAST
    ↓
STAFF

この場合、

HistoryType
    = PARTICIPATION

ParticipantType
    = STAFF

とする。

---

## Subject Changed

Subjectが変更された場合、
Participation HistoryのSubjectを更新する。

SubjectはParticipantが参照する活動主体を表す。

PersonからOrganization、
またはOrganizationからPersonへの変更も、
Participantの変更として扱う。

---

## Production Changed

Productionが変更された場合、
Participation HistoryのProductionを更新する。

ProductionはHistoryに必須である。

---

## Role Changed

Roleが変更されても、
Participation Historyは更新しない。

RoleはParticipantにおける具体的な役割名称であり、
HistoryのParticipantTypeとは異なる概念である。

---

## CreditOrder Changed

CreditOrderが変更されても、
Participation Historyは更新しない。

CreditOrderはクレジット表示順を表す情報であり、
活動実績そのものではない。

---

## Visibility Changed

Visibilityが変更されても、
Participation Historyは更新しない。

VisibilityはParticipant情報の公開状態を表す。

---

## Status Changed

Statusの変更だけでは、
Participation Historyを更新しない。

StatusはParticipantの現在状態を表す。

過去の活動実績とは別の概念として扱う。

---

# Subject

ParticipantUpdatedはSubjectIdをEvent Payloadに含める。

History DomainはSubjectIdを使用して、
Participation HistoryのSubjectを決定する。

Subjectは以下のいずれかを表す。

PERSON
ORGANIZATION

Participant DomainはPersonまたはOrganizationを
直接History Domainへ渡さない。

Subjectを通じて活動主体を識別する。

---

# Participant Type

ParticipantUpdatedはParticipantTypeをEvent Payloadに含める。

ParticipantTypeが変更された場合、
History Domainは更新後のParticipantTypeを
Participation Historyへ反映する。

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

ParticipantUpdatedはProductionIdをEvent Payloadに含める。

History DomainはProductionIdを使用して、
Participation HistoryのProductionを決定する。

Productionは必須である。

---

# Performance

ParticipantUpdatedによって更新される
Participation Historyでは、
Performanceを設定しない。

Performance = NULL

ParticipantはProductionへの参加を表すため、
特定のPerformanceへの参加とは区別する。

---

# Event Handler

History DomainはParticipantUpdatedを受信する。

ParticipantUpdated
        ↓
ParticipationHistoryHandler
        ↓
変更内容を確認
        ↓
必要な場合のみHistory更新

Event HandlerはParticipant Domainの内部実装を
直接操作しない。

---

# Idempotency

ParticipantUpdatedの処理は冪等であること。

同じeventIdを複数回受信した場合でも、
Historyを重複して更新しない。

ParticipantUpdated
eventId = event-002

1回目
    ↓
History更新

2回目
    ↓
処理済み
    ↓
History更新しない

---

# Failure Handling

History更新に失敗した場合でも、
Participantの更新自体を取り消さない。

ParticipantUpdatedは発行済みEventとして保持し、
Event Handlerが再実行できる状態とする。

---

# Business Rules

- ParticipantUpdatedはParticipant Domainが発行する。
- ParticipantUpdatedはParticipant更新完了後に発行する。
- ParticipantUpdatedはPersonまたはOrganizationの更新を意味しない。
- ParticipantUpdatedはHistoryを直接操作しない。
- History DomainはParticipantUpdatedを契機として変更内容を確認する。
- ParticipantTypeの変更はParticipation Historyへ反映する。
- Subjectの変更はParticipation Historyへ反映する。
- Productionの変更はParticipation Historyへ反映する。
- Roleの変更はParticipation Historyへ反映しない。
- CreditOrderの変更はParticipation Historyへ反映しない。
- Visibilityの変更はParticipation Historyへ反映しない。
- Statusの変更はParticipation Historyへ反映しない。
- PerformanceはNULLとする。
- Event処理は冪等である。
- Event Handlerの失敗によってParticipant更新を取り消さない。

---

# Design Principles

- Eventは発生したBusiness Eventを表現する。
- Event PublisherとEvent Handlerを分離する。
- Participant DomainはHistory Domainへ直接依存しない。
- History DomainはParticipant Domainへ直接依存しない。
- Event Payloadには後続処理に必要な情報を含める。
- Historyに影響する変更と表示上の変更を区別する。
- 同一Eventの重複処理を防止する。
- HistoryはDomain Eventを契機として更新する。