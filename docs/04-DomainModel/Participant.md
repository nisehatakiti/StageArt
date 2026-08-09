# StageArt Blueprint
# Domain Model : Participant

Version : 2.1

---

# Purpose

ParticipantはProductionへ参加する活動主体を表すDomainである。

Participantは出演者だけではなく、

- キャスト
- スタッフ
- 演出
- 制作
- 主催
- 共催
- 協賛
- 後援

など、公演へ参加するすべての活動主体を表現する。

ParticipantはProductionとSubjectを関連付けるBusiness Domainである。

---

# Concept

ParticipantはProductionへの参加を表す。

参加主体はSubjectによって表現する。

SubjectはPersonまたはOrganizationを参照する。

```
Production
        │
        ▼
   Participant
        │
        ▼
     Subject
     ├── Person
     └── Organization
```

ParticipantはSubjectを介して活動主体を参照する。

PersonおよびOrganizationへ直接依存しない。

---

# Responsibility

Participantは以下を管理する。

- Subject
- ParticipantType
- Role
- CreditOrder
- Visibility
- Status

Productionとの関連付けはParticipantが管理する。

活動履歴(History)は管理しない。

---

# Identity

ParticipantはParticipantIdによって識別する。

ParticipantIdは変更できない。

Productionを変更する場合は
新しいParticipantとして扱う。

---

# Subject

ParticipantはSubjectを保持する。

Subjectは以下で構成される。

- SubjectType
- SubjectId

Subjectは以下を参照できる。

- Person
- Organization

Version 1.0では上記のみをサポートする。

---

# Participant Type

ParticipantTypeは公演への参加区分を表す。

例）

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- SPONSOR
- SUPPORTER

ParticipantTypeはBusiness Ruleおよび検索・集計に利用する。

将来的に追加可能である。

---

# Role

Roleは公演内での役割名称を表す。

例）

- 主演
- 演出
- 音響
- 照明
- 舞台監督
- 主催
- 協賛

Roleは表示情報であり、
Business Ruleには利用しない。

---

# Credit Order

CreditOrderは表示順を表す。

小さい値ほど先に表示する。

同順位を許可する。

---

# Visibility

Visibilityは公開状態を表す。

例）

- PUBLIC
- PRIVATE

非公開Participantは管理画面のみ表示する。

---

# Status

ParticipantStatusはParticipantの状態を表す。

例）

- ACTIVE
- INACTIVE

論理削除はStatusによって管理する。

---

# Business Rules

Participantは必ず一つのProductionへ所属する。

Participantは必ず一つのSubjectを持つ。

SubjectはPersonまたはOrganizationである。

一つのParticipantが複数のSubjectを持つことはできない。

Production内で同一Subjectを重複登録できるかどうかは、
ParticipantTypeを含めたBusiness Ruleによって判定する。

ParticipantはHistoryを生成・更新・削除しない。

HistoryはParticipantが発行するDomain Eventによって
別Domainが生成・更新する。

---

# Domain Events

Participantは以下のDomain Eventを発行する。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

HistoryはこれらのDomain Eventを契機として
自動生成・更新される。

ParticipantはHistoryを意識しない。

---

# Design Decisions

ParticipantはProductionへの参加を表す。

ParticipantはSubjectを通じて活動主体を参照する。

ParticipantはPersonおよびOrganizationへ直接依存しない。

ParticipantはHistoryへ依存しない。

Historyは独立したDomainとして管理する。

---

# Design Principles

- ParticipantはProductionへの参加を表すBusiness Domainである。
- Subjectは活動主体を表す共通Referenceである。
- ParticipantはSubjectのみを参照する。
- PersonおよびOrganizationへ直接依存しない。
- ParticipantTypeはシステムが管理する参加区分である。
- Roleは表示情報である。
- ParticipantはHistoryを管理しない。
- HistoryはDomain Eventによって管理する。
- ParticipantはBusiness Ruleのみを管理する。
