# StageArt Blueprint
# Domain Model : History

Version : 4.0

---

# Purpose

HistoryはStageArtにおける活動履歴を表す独立したDomainである。

HistoryはPersonやOrganizationの子Entityではなく、
Subjectを中心として活動履歴を管理する。

Historyは利用者が直接作成・編集するDomainではない。

ParticipantやReservationなどのDomainで発生した
Domain Eventを契機として、
History Domainが必要な履歴を生成・更新する。

---

# Concept

HistoryはSubjectがStageArt上で行った活動を記録する。

Subject
    │
    ▼
History
    │
    ├── Production
    └── Performance

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

以下をサポートする。

- PARTICIPATION
- AUDIENCE

## PARTICIPATION

Productionへの参加を表す。

Participantによって発生する。

## AUDIENCE

観客としてPerformanceを観覧した事実を表す。

ReservationCheckedInを契機として発生する。

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

HistoryTypeがAUDIENCEの場合、
ParticipantTypeは保持しない。

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

Production
    = 12人のうかれる人々

Performance
    = NULL

観劇履歴

Production
    = 12人のうかれる人々

Performance
    = 2026-10-12 18:00

---

# Event Date Time

EventDateTimeは、
Historyが表す活動が発生した日時を記録する。

活動の性質に応じて、
ProductionまたはPerformanceに関連する日時を使用する。

Audience Historyでは、
ReservationCheckedInが発生した日時を基準とする。

---

# Generation

HistoryはDomain Eventを契機として生成・更新される。

Historyを生成する元のDomainが、
Historyを直接操作することはない。

代表的な生成・更新契機は以下の通り。

---

# Participant Added

ParticipantAdded
        ↓
History

HistoryType
    = PARTICIPATION

ParticipantType
    = Participant.ParticipantType

ParticipantがProductionへ追加された時点で、
Participation Historyを生成する。

---

# Participant Updated

Participantの変更内容に応じて、
Historyを更新する。

ParticipantTypeの変更はHistoryへ反映する。

Role、CreditOrder、Visibilityなど、
Historyの活動内容そのものに影響しない情報の変更は
Historyを変更しない。

---

# Participant Removed

Participantが削除された場合でも、
過去に存在した活動実績を消去しない。

既に生成されたHistoryは保持する。

ParticipantRemovedは、
過去のHistoryを削除するためには使用しない。

---

# Reservation Checked In

ReservationCheckedIn
        ↓
History

HistoryType
    = AUDIENCE

ReservationのCheck Inによって、
観客として実際に来場した事実をHistoryとして記録する。

ReservationCreatedだけではAudience Historyを生成しない。

ReservationUpdatedだけではAudience Historyを生成しない。

ReservationCancelledだけではAudience Historyを生成しない。

---

# Audience History Subject

Audience HistoryのSubjectは、
ReservationのBookerを使用する。

Reservation
    ↓
Booker
    ↓
Audience History.Subject

例えば、

Booker
    = Person A

HandledParticipant
    = Participant B

の場合、

Audience History.Subject
    = Person A

となる。

HandledParticipantは、
Audience HistoryのSubjectにはならない。

HandledParticipantは予約における
「○○扱い」を表す情報であり、
観客としての活動主体を表すものではない。

---

# History and Reservation

ReservationはHistoryを管理しない。

Reservationは以下のDomain Eventを発行する。

- ReservationCreated
- ReservationUpdated
- ReservationCheckedIn
- ReservationCancelled

History Domainは、
ReservationCheckedInを契機として
Audience Historyを生成する。

以下のEventではAudience Historyを生成しない。

- ReservationCreated
- ReservationUpdated
- ReservationCancelled

予約した事実、
予約内容を変更した事実、
予約をキャンセルした事実と、
実際に観劇した事実は別の概念として扱う。

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

そのため、
HandledParticipantの指定や変更だけでは
Historyを生成・更新しない。

観客としての来場履歴は、
ReservationCheckedInを契機として生成される。

Audience HistoryのSubjectは、
Reservation.Bookerである。

---

# History and Created By

ReservationのCreatedByは、
Reservationを作成した主体を表す。

CreatedByは、
Audience HistoryのSubjectには使用しない。

Audience HistoryのSubjectは、
ReservationのBookerである。

例えば、

Booker
    = Person A

CreatedBy
    = Participant B

の場合でも、

Audience History.Subject
    = Person A

となる。

CreatedByは、
「誰が予約を入力したか」を表す情報であり、
「誰が観劇したか」を表す情報ではない。

---

# History and Updated By

ReservationのUpdatedByは、
Reservationを最後に変更した主体を表す。

UpdatedByは、
Audience HistoryのSubjectには使用しない。

Audience HistoryのSubjectは、
ReservationのBookerである。

ReservationUpdatedによって
UpdatedByが変更された場合でも、
Audience Historyは生成・更新しない。

---

# Read Model

Historyは読み取りを中心としたDomainである。

Historyそのものを編集するための公開APIは提供しない。

必要に応じて、
他のBusiness Resource APIが
Historyを集約して返す。

例）

Person API
    ↓
Subject = PERSON
    ↓
History[]

Organization API
    ↓
Subject = ORGANIZATION
    ↓
History[]

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

ReservationUpdatedだけではAudience Historyを生成しない。

ReservationCancelledだけではAudience Historyを生成しない。

ReservationCheckedInによってAudience Historyを生成する。

Audience HistoryのSubjectはReservation.Bookerである。

HandledParticipantはAudience HistoryのSubjectにならない。

CreatedByはAudience HistoryのSubjectにならない。

UpdatedByはAudience HistoryのSubjectにならない。

HandledParticipantの指定・変更だけでは
Historyを生成・更新しない。

Reservationの変更だけでは
Audience Historyを生成・更新しない。

ReservationのCheck Inによって
観劇実績を確定する。

---

# Data Examples

## Participation History

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

---

## Staff History

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

---

## Audience History

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

---

# Domain Boundaries

History Domainは以下のDomainから
Domain Eventを受け取る。

Participant
    ↓
ParticipantAdded
ParticipantUpdated
ParticipantRemoved
    ↓
History Domain

Reservation
    ↓
ReservationCheckedIn
    ↓
History Domain

History Domainは、
これらのDomainへ直接依存しない。

History Domainは受け取ったEventをもとに
Historyを生成・更新する。

---

# Domain Events

History Domainが利用する主なDomain Event

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved
- ReservationCheckedIn

ReservationCreated、
ReservationUpdated、
ReservationCancelledは、
Audience History生成の契機として使用しない。

History Domain自身が
これらの元Domainへ直接変更を要求することはない。

---

# Idempotency

History生成処理は冪等であること。

同一のDomain Eventが複数回処理された場合でも、
同一の活動を重複してHistoryへ記録しない。

特にReservationCheckedInについては、
同一ReservationのCheck Inから
同一Audience Historyを複数生成しない。

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

BookerはAudience Historyの活動主体である。

CreatedByはAudience Historyの活動主体ではない。

UpdatedByはAudience Historyの活動主体ではない。

予約と観劇実績を分離して管理する。

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
- ReservationCreatedではAudience Historyを生成しない。
- ReservationUpdatedではAudience Historyを生成しない。
- ReservationCancelledではAudience Historyを生成しない。
- ReservationCheckedInをAudience History生成の契機とする。
- Audience HistoryのSubjectはReservation.Bookerである。
- HandledParticipantはHistoryの活動主体ではない。
- CreatedByはHistoryの活動主体ではない。
- UpdatedByはHistoryの活動主体ではない。
- SeatおよびReservationSeatはHistoryの活動主体ではない。
- 過去の活動実績は原則として削除しない。
- History生成処理は冪等性を確保する。
