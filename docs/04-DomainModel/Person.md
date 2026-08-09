# StageArt Blueprint
# Domain Model : Person

Version : 2.0

---

# Purpose

PersonはStageArtに登録される人物を表すDomainである。

Personは認証情報(Account)とは独立したBusiness Domainであり、
プロフィールおよび活動主体としての情報を管理する。

PersonはOrganizationへの所属、Productionへの参加、
観劇など様々なBusiness Activityの主体となる。

---

# Concept

PersonはStageArtにおける人物を表現する。

認証(Account)とは独立して存在し、
Business上の主体として利用される。

```
Account
    │
    ▼
 Person
    │
    ├── Organization
    ├── Participant
    └── History
```

HistoryはPersonが保持するものではなく、
Subjectを介して関連付けられる。

---

# Responsibility

Personは以下を管理する。

- Display Name
- Profile
- Contact Information
- Public Settings
- Status

活動履歴(History)は管理しない。

---

# Identity

PersonはPersonIdによって識別する。

PersonIdは変更できない。

---

# Profile

Profileは人物紹介を表す。

例）

- Biography
- Profile Image
- Website
- SNS

Profileは公開設定に従って表示する。

---

# Organization

Personは複数のOrganizationへ所属できる。

所属情報はMembershipによって管理する。

PersonはOrganizationを直接管理しない。

---

# Participant

PersonはProductionへ参加できる。

Productionとの関係はParticipantによって管理する。

PersonはParticipantを直接保持しない。

---

# History

PersonはHistoryを直接保持しない。

Historyは独立したDomainであり、
Subjectを介してPersonへ関連付けられる。

Person APIでは、
SubjectがPersonであるHistoryを集約して公開する。

Historyは以下を含む。

- Participation History
- Audience History

HistoryはDomain Eventによって自動生成・更新される。

---

# Status

PersonStatusは人物の状態を表す。

例）

- ACTIVE
- INACTIVE

論理削除はStatusによって管理する。

---

# Business Rules

PersonはBusiness Activityの主体となる。

PersonはOrganizationへ所属できる。

PersonはProductionへ参加できる。

Personは観客としてReservationを作成できる。

Historyは利用者が編集できない。

HistoryはDomain Eventによってのみ生成・更新される。

---

# Domain Events

Personは以下のDomain Eventと関連する。

- PersonCreated
- PersonProfileUpdated
- PersonArchived

Historyは以下のDomain Eventによって更新される。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved
- ReservationCreated
- ReservationCheckedIn
- ReservationCancelled

---

# Design Decisions

Personは人物を表すBusiness Domainである。

PersonはAccountへ依存しない。

PersonはParticipantを保持しない。

PersonはHistoryを保持しない。

HistoryはSubjectによって関連付けられる独立Domainである。

---

# Design Principles

- Personは人物を表すBusiness Domainである。
- PersonはAccountとは独立したDomainである。
- PersonはBusiness Activityの主体である。
- Organizationとの関係はMembershipで管理する。
- Productionとの関係はParticipantで管理する。
- Historyは独立したDomainである。
- HistoryはSubjectを介してPersonへ関連付けられる。
- HistoryはDomain Eventによって自動生成・更新される。
- PersonはBusiness Ruleのみを管理する。
