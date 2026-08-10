# StageArt Blueprint
# Domain Model : History

Version : 3.0

---

# Purpose

HistoryはStageArtにおける活動履歴を表す独立したDomainである。

HistoryはPersonやOrganizationの子Entityではなく、
Subjectを中心として活動履歴を管理する。

Historyは利用者が直接作成・編集するDomainではない。

ParticipantやReservationなどのDomainで発生した
Domain Eventを契機として、History Domainが必要な履歴を生成・更新する。

---

# Concept

HistoryはSubjectがStageArt上で行った活動を記録する。

```text
Subject
   │
   ▼
History
   │
   ├── Production
   └── Performance
```

SubjectはPersonまたはOrganizationを参照する。

HistoryはSubject自身の属性ではなく、
SubjectとStageArt上の活動との関係を記録する。

---

# Responsibility

Historyは以下を管理する。

- Subject
- HistoryType
- ParticipantType
- Production
- Performance
- EventDateTime

Historyは活動履歴の記録を担当する。

ParticipantやReservationなど、
他DomainのBusiness Ruleは管理しない。

---

# Identity

HistoryはHistoryIdによって識別する。

HistoryIdは変更できない。

同一の活動を重複して記録しないよう、
必要に応じてDomain Eventとの対応関係を利用して
冪等性を確保する。

---

# Subject

HistoryはSubjectを保持する。

Subjectは以下で構成される。

- SubjectType
- SubjectId

SubjectTypeは以下をサポートする。

- PERSON
- ORGANIZATION

HistoryはPersonやOrganizationを直接参照しない。

Subjectを介して活動主体を参照する。

---

# History Type

HistoryTypeは活動の種類を表す。

Version 1.0では以下をサポートする。

- PARTICIPATION
- AUDIENCE

## PARTICIPATION

Productionへの参加を表す。

Participantによって生成される。

## AUDIENCE

観客としてPerformanceを観覧した事実を表す。

ReservationCheckedInを契機として生成される。

---

# Participant Type

ParticipantTypeは、
HistoryTypeがPARTICIPATIONの場合にのみ保持する。

ParticipantTypeはProductionへの参加区分を表す。

例）

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- SPONSOR
- SUPPORTER

ParticipantTypeはParticipantで定義される値を使用する。

HistoryはParticipantTypeそのもののBusiness Ruleを定義しない。

---

# Production

Historyは活動対象となったProductionを保持する。

Productionは必須である。

Productionによって、
どの公演・作品に関する活動だったかを識別する。

---

# Performance

Historyは必要に応じてPerformanceを保持する。

Performanceは任意である。

Production単位の活動ではPerformanceを保持しない。

特定の公演回に紐付く活動ではPerformanceを保持する。

例）

出演実績

```text
Production : 12人のうかれる人々
Performance : NULL
```

観劇履歴

```text
Production : 12人のうかれる人々
Performance : 2026-10-12 18:00
```

---

# Event Date Time

EventDateTimeは、
Historyが表す活動が発生した日時を記録する。

活動の性質に応じて、
ProductionまたはPerformanceに関連する日時を使用する。

---

# Generation

HistoryはDomain Eventを契機として生成・更新される。

Historyを生成する元のDomainが、
Historyを直接操作することはない。

代表的な生成・更新契機は以下の通り。

## Participant Added

```text
ParticipantAdded
        ↓
History
HistoryType = PARTICIPATION
ParticipantType = Participant.ParticipantType
```

ParticipantがProductionへ追加された時点で、
Participation Historyを生成する。

---

## Participant Updated

Participantの変更内容に応じて、
Historyを更新する。

ParticipantTypeの変更はHistoryへ反映する。

Role、CreditOrder、Visibilityなど、
Historyの活動内容そのものに影響しない情報の変更は
Historyを変更しない。

---

## Participant Removed

Participantが削除された場合でも、
過去に存在した活動実績を消去しない。

既に生成されたHistoryは保持する。

ParticipantRemovedは、
過去のHistoryを削除するためには使用しない。

---

## Reservation Checked In

```text
ReservationCheckedIn
        ↓
History
HistoryType = AUDIENCE
```

ReservationのCheck Inによって、
観客として実際に来場した事実をHistoryとして記録する。

ReservationCreatedだけではAudience Historyを生成しない。

---

# History and Reservation

ReservationはHistoryを管理しない。

ReservationはReservationCheckedInを発行する。

History DomainはReservationCheckedInを契機として、
必要なAudience Historyを生成する。

以下のイベントではAudience Historyを生成しない。

- ReservationCreated
- ReservationCancelled

予約した事実と観劇した事実は別の概念として扱う。

---

# History and Participant

ParticipantはHistoryを管理しない。

Participantは以下のDomain Eventを発行する。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

History DomainはこれらのEventを契機として、
Participation Historyを生成・更新する。

ParticipantがHistoryを直接操作することはない。

---

# History and Handled Participant

HandledParticipantはReservationにおける
予約の「扱い」を表す。

HandledParticipantはParticipantを参照する。

HandledParticipantは予約を担当するParticipantを表すが、
それ自体は新しい活動履歴を意味しない。

そのため、HandledParticipantの指定や変更だけでは
Historyを生成・更新しない。

観客としての来場履歴は、
ReservationCheckedInを契機として生成される。

---

# Read Model

Historyは読み取りを中心としたDomainである。

Historyそのものを編集するための公開APIは提供しない。

必要に応じて、他のBusiness Resource APIが
Historyを集約して返す。

例）

```text
Person API
    ↓
Subject = PERSON
    ↓
History[]
```

```text
Organization API
    ↓
Subject = ORGANIZATION
    ↓
History[]
```

Historyは独立Domainとして保持されるが、
利用者からはPersonやOrganizationなどの
Business Resourceを通して参照できる。

---

# Business Rules

Historyは必ず一つのSubjectに属する。

Historyは必ず一つのProductionを参照する。

Performanceは任意である。

HistoryTypeがPARTICIPATIONの場合、
ParticipantTypeを保持する。

HistoryTypeがAUDIENCEの場合、
ParticipantTypeは保持しない。

Historyは利用者が直接作成・編集・削除しない。

HistoryはDomain Eventを契機として生成・更新する。

ParticipantRemovedによって過去のHistoryを削除しない。

ReservationCreatedだけではAudience Historyを生成しない。

ReservationCancelledだけではAudience Historyを生成しない。

ReservationCheckedInによってAudience Historyを生成する。

HandledParticipantの指定・変更だけではHistoryを生成・更新しない。

---

# Data Examples

## Participation History

```json
{
  "historyType": "PARTICIPATION",
  "subject": {
    "type": "PERSON",
    "id": "person-001"
  },
  "participantType": "CAST",
  "productionId": "production-001",
  "performanceId": null
}
```

---

## Staff History

```json
{
  "historyType": "PARTICIPATION",
  "subject": {
    "type": "PERSON",
    "id": "person-002"
  },
  "participantType": "STAFF",
  "productionId": "production-001",
  "performanceId": null
}
```

---

## Audience History

```json
{
  "historyType": "AUDIENCE",
  "subject": {
    "type": "PERSON",
    "id": "person-003"
  },
  "participantType": null,
  "productionId": "production-001",
  "performanceId": "performance-002"
}
```

---

# Domain Boundaries

History Domainは以下のDomainから
Domain Eventを受け取る。

```text
Participant
Reservation
```

History Domainは、
これらのDomainへ直接依存しない。

```text
Participant
     │
     │ ParticipantAdded
     │ ParticipantUpdated
     │ ParticipantRemoved
     ▼
 History Domain
```

```text
Reservation
     │
     │ ReservationCheckedIn
     ▼
 History Domain
```

History Domainは受け取ったEventをもとに
Historyを生成・更新する。

---

# Domain Events

History Domainが利用する主なDomain Event

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved
- ReservationCheckedIn

History Domain自身が
これらの元Domainへ直接変更を要求することはない。

---

# Design Decisions

Historyは独立したDomainとして管理する。

HistoryはPersonやOrganizationの子Entityではない。

HistoryはSubjectを介して活動主体を参照する。

HistoryTypeは活動の種類を表す。

ParticipantTypeはProductionへの参加区分を表す。

HistoryTypeとParticipantTypeは異なる責務を持つ。

HistoryはParticipantやReservationのBusiness Ruleを持たない。

History生成はDomain Eventを契機として行う。

過去の活動実績は原則として削除しない。

HandledParticipantはHistoryの活動主体ではない。

---

# Design Principles

- Historyは活動履歴を表す独立Domainである。
- HistoryはSubjectを中心として管理する。
- HistoryはPersonおよびOrganizationへ直接依存しない。
- HistoryTypeは活動の種類を表す。
- ParticipantTypeは参加区分を表す。
- ParticipantTypeはPARTICIPATIONの場合のみ使用する。
- HistoryはProductionを必ず参照する。
- Performanceは必要な場合のみ参照する。
- Historyは利用者が直接編集しない。
- HistoryはDomain Eventによって生成・更新される。
- ParticipantはHistoryを管理しない。
- ReservationはHistoryを管理しない。
- HandledParticipantはHistoryを生成する理由にならない。
- 過去の活動実績は原則として削除しない。