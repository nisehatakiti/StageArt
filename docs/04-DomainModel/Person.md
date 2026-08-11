# StageArt Blueprint

# Domain Model : Person

Version : 3.0

---

# Purpose

PersonはStageArtにおける個人Identityを表すDomainである。

Personは認証情報(Account)とは独立したBusiness Domainであり、
舞台芸術活動、Organizationへの所属、Productionへの参加、
観客としての利用など、StageArt上の個人に関するBusiness Activityの主体となる。

PersonはOrganizationとは独立して存在する。

一人のPersonは複数のOrganizationに所属することができ、
Organizationごとに異なるRoleを持つことができる。

---

# Concept

PersonはStageArt上の個人を表現する。

Personは、

- 役者
- スタッフ
- 制作
- 観客
- その他の舞台芸術関係者

などを区別せず、一つのPersonとして扱う。

認証(Account)とは独立して存在し、
Business上の主体として利用される。

```text
Account
    │
    ▼
 Person
    │
    ├── Profile
    │
    ├── Membership
    │      │
    │      └── Organization
    │
    ├── Participant
    │      │
    │      └── Production
    │
    ├── Reservation
    │      │
    │      └── Check In
    │
    └── History
```

---

# Responsibility

Personは以下の情報およびBusiness上のIdentityを表す。

- Person Identity
- Display Name
- Profile
- Contact Information
- Public Settings
- Status

Organizationへの所属はMembershipで管理する。

Productionへの参加はParticipantで管理する。

チケット予約はReservationで管理する。

活動履歴はHistoryで管理する。

---

# Identity

PersonはPersonIdによって識別する。

PersonIdは変更できない。

PersonとAccountは同一概念ではない。

Accountは認証Identityを表し、
PersonはStageArt上のBusiness Identityを表す。

---

# Profile

ProfileはPerson自身が作成・編集できるプロフィール情報を表す。

Profileには必要に応じて、

- Display Name
- Biography
- Profile Image
- Website
- SNS
- その他プロフィール情報

などを登録する。

ProfileはPerson本人による入力を許可する。

出演実績や観劇履歴などの活動履歴はProfileへ直接入力するのではなく、
Historyから参照・表示する。

Profileの公開範囲は、
StageArtにおける公開ルールに従う。

---

# Organization

Personは複数のOrganizationへ所属できる。

所属情報はMembershipによって管理する。

PersonはOrganizationを直接保持しない。

```text
Person
    │
    └── Membership
             │
             └── Organization
```

同じPersonでも、
Organizationごとに異なるRoleを持つことができる。

例：

```text
Person A
    │
    ├── Membership
    │      └── 劇団A
    │             └── Role = 管理者
    │
    └── Membership
           └── 劇団B
                  └── Role = キャスト
```

---

# Participant

PersonはProductionへ参加できる。

Productionとの関係はParticipantによって管理する。

PersonはParticipantを直接保持するものではなく、
ParticipantのSubjectとして参照される。

```text
Production
    │
    └── Participant
            │
            └── Subject
                   │
                   └── Person
```

Participantによって、

- キャスト
- スタッフ
- 制作
- 客演

などのProductionへの参加を表現する。

---

# Reservation

Personは観客としてProductionのチケットを予約できる。

Reservationは観客による予約というFactを表す。

```text
Person
    │
    └── Reservation
            │
            └── Performance
```

Reservation成立後にIssued Ticketが発行され、
公演当日のCheck Inによって来場が記録される。

---

# History

HistoryはStageArtにおける活動履歴を管理する重要なDomainである。

PersonはHistoryを直接編集するのではなく、
Historyから自身に関連する履歴を参照する。

Personに関連するHistoryには、

- 出演履歴
- スタッフ履歴
- 観劇履歴
- その他活動履歴

などが含まれる。

```text
Person
    │
    └── History
         ├── 出演履歴
         ├── スタッフ履歴
         └── 観劇履歴
```

出演・スタッフ履歴はParticipant等のFactから生成される。

観劇履歴は、

```text
Reservation
    ↓
Issued Ticket
    ↓
Check In
    ↓
History
```

というFactの流れから生成される。

StageArtユーザーとして登録していない一般観客については、
Personを作成する必要はない。

チケット予約・受付などの必要な情報のみを管理する。

StageArtユーザーとして登録した観客はPersonとして管理され、
自身の観劇履歴を確認できる。

Historyの具体的な管理ルールはHistory Domainで定義する。

---

# Status

PersonStatusは人物の状態を表す。

例：

- ACTIVE
- INACTIVE

論理削除が必要な場合はStatusによって管理する。

PersonIdそのものは変更・再利用しない。

---

# Business Rules

PersonはBusiness Activityの主体となる。

Personは複数のOrganizationへ所属できる。

PersonのOrganizationへの所属はMembershipによって管理する。

PersonはProductionへ参加できる。

Productionへの参加はParticipantによって管理する。

Personは観客としてReservationを作成できる。

StageArtユーザーとして登録した観客は、
自身の観劇履歴を確認できる。

一般観客はStageArtのInternal Portalへ参加する必要はない。

ProfileはPerson本人が入力・編集できる。

出演実績・スタッフ実績・観劇履歴などのFact由来のHistoryは、
Personが直接編集しない。

---

# Domain Events

Personは以下のDomain Eventと関連する。

- PersonCreated
- PersonProfileUpdated
- PersonArchived

Personに関連するHistoryは、
関連するBusiness Eventによって生成・更新される。

主なEvent：

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved
- ReservationCreated
- ReservationCheckedIn
- ReservationCancelled

Historyの生成・更新ルールはHistory Domainで定義する。

---

# Design Decisions

Personは人物を表すBusiness Domainである。

PersonはAccountとは独立したDomainである。

Accountは認証Identity、
PersonはBusiness Identityを表す。

PersonとOrganizationの関係はMembershipで管理する。

PersonとProductionの関係はParticipantで管理する。

PersonはReservationの主体となることができる。

ProfileはPerson本人が入力・編集できる。

HistoryはPersonとは独立したDomainとして管理する。

Personに関連するHistoryは、
History Domainのルールに従って参照・表示する。

Companion Domainは存在しない。

---

# Design Principles

- Personは人物を表すBusiness Domainである。
- PersonはAccountとは独立したDomainである。
- Accountは認証Identity、PersonはBusiness Identityを表す。
- PersonはBusiness Activityの主体である。
- PersonとOrganizationの関係はMembershipで管理する。
- PersonとProductionの関係はParticipantで管理する。
- ProfileはPerson本人が作成・編集できる。
- Personは観客としてReservationを作成できる。
- StageArtユーザーとして登録した観客は観劇履歴を確認できる。
- 一般観客はInternal Portalを利用しない。
- Historyは重要な独立Domainとして扱う。
- HistoryはBusiness Factから生成・更新する。
- Companionは管理しない。
