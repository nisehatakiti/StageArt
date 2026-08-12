# StageArt Blueprint

# Domain Model : Person

Version : 4.1

---

# Purpose

PersonはStageArtにおける個人Identityを表すDomainである。

Personは認証情報(UserAccount)とは独立したBusiness Domainであり、
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

認証(UserAccount)とは独立して存在し、
Business上の主体として利用される。

基本構造：

UserAccount
    │
    ▼
  Person
    │
    ├── Profile
    │      │
    │      └── HistoricalActivity[]
    │
    ├── Membership
    │      │
    │      └── Organization
    │
    ├── Participant
    │      │
    │      └── Production
    │
    └── Reservation
           │
           └── Performance

HistoryはPersonの子Entityではなく、
独立したDomainとして管理する。

Personに関連するHistoryは、
History Domainから参照・表示する。

---

# Responsibility

Personは以下の情報およびBusiness上のIdentityを表す。

- Person Identity
- Profile
- Contact Information
- Public Settings
- Status

Organizationへの所属はMembershipで管理する。

Productionへの参加はParticipantで管理する。

チケット予約はReservationで管理する。

Profileに関する本人入力情報はProfileで管理する。

StageArt上で発生した活動履歴はHistoryで管理する。

---

# Identity

PersonはPersonIdによって識別する。

PersonIdは変更できない。

PersonとUserAccountは同一概念ではない。

UserAccountは認証Identityを表し、
PersonはStageArt上のBusiness Identityを表す。

---

# UserAccount Relationship

UserAccountは認証情報を表す。

PersonはBusiness上の個人Identityを表す。

基本構造：

UserAccount
    │
    ▼
Person

UserAccountが存在することと、
PersonがStageArt上でどのBusiness Activityを行うかは
別の概念として扱う。

---

# Profile

ProfileはPerson自身が作成・編集できる
プロフィール情報を表す。

ProfileはPersonに1つだけ存在する。

基本構造：

Person
    │
    └── Profile

Profileには必要に応じて、

- Biography
- Profile Image
- Website
- SNS
- HistoricalActivity[]

などを保持する。

ProfileはPerson本人による入力・編集を許可する。

Profileの詳細な管理ルールはProfile Domainで定義する。

---

# HistoricalActivity

HistoricalActivityは、
Profileに登録する本人申告の過去活動実績を表す。

例えば、

- StageArt登録前の出演歴
- StageArt登録前のスタッフ歴
- 過去の演出歴
- 過去の制作歴
- 過去の観劇歴
- その他本人が紹介したい活動実績

など。

基本構造：

Person
    │
    └── Profile
           │
           └── HistoricalActivity[]

HistoricalActivityはProfileの子Entityであり、
Personから直接管理しない。

HistoricalActivityの詳細な構造・ルールは
HistoricalActivity Domainで定義する。

---

# HistoricalActivity and History

HistoricalActivityとHistoryは異なる概念である。

HistoricalActivity：

Person本人がProfileへ登録する
過去の活動実績。

History：

StageArt上で発生したFactから
自動生成される活動履歴。

基本構造：

本人申告の過去活動

Person
    │
    └── Profile
           │
           └── HistoricalActivity


StageArt上の活動

Participant / Check In
    │
    ▼
Domain Event
    │
    ▼
History

HistoricalActivityをHistoryへ変換しない。

HistoryをHistoricalActivityへ複製しない。

---

# History

HistoryはStageArtにおける活動履歴を管理する
独立したDomainである。

HistoryはPersonの子Entityではない。

Personに関連するHistoryは、
History Domainから参照・表示する。

例えば、

- 出演履歴
- スタッフ履歴
- 観劇履歴

などがある。

基本構造：

Person
    │
    └── History Reference

History
    │
    ├── PARTICIPATION
    └── AUDIENCE

Historyの具体的な管理ルールは
History Domainで定義する。

---

# Participation History

PersonがStageArt上でProductionへ
参加した実績は、
Participantを起点としてHistoryに記録される。

基本Flow：

Production
    ↓
Participant
    ↓
ParticipantAdded
    ↓
History

PersonはHistoryを直接作成・編集しない。

---

# Audience History

PersonがStageArt上で
観客として観劇した実績は、
Check In完了を契機としてHistoryに記録される。

基本Flow：

Reservation
    ↓
Check In
    ↓
CheckInCompleted
    ↓
History

予約しただけでは、
観劇履歴として扱わない。

---

# Audience Identity

Audience HistoryのSubjectは、
ReservationのBookerを基本とする。

例えば、

Reservation
    │
    ├── Booker
    │      └── Person A
    │
    └── Check In
           │
           └── CheckInCompleted
                  │
                  ▼
              Audience History
                  │
                  └── Subject = Person A

HandledParticipantは、
Audience HistoryのSubjectにはならない。

CreatedByも、
Audience HistoryのSubjectにはならない。

UpdatedByも、
Audience HistoryのSubjectにはならない。

---

# General Audience

一般観客は、
StageArtのInternal Portalへ参加する必要はない。

チケット購入や公演当日の受付に必要な情報のみを
管理できる。

一般観客について、
必ずPersonを作成する必要はない。

---

# Registered Audience

StageArtユーザーとして登録した観客は、
Personとして管理できる。

Personとして登録された観客は、
自身の観劇履歴を確認できる。

基本構造：

Person
    │
    └── Audience History Reference

観劇履歴そのものは、
History Domainが管理する。

---

# Organization

Personは複数のOrganizationへ所属できる。

所属情報はMembershipによって管理する。

PersonはOrganizationを直接保持しない。

基本構造：

Person
    │
    └── Membership
             │
             └── Organization

同じPersonでも、
Organizationごとに異なるRoleを持つことができる。

例えば、

Person A
    │
    ├── Membership
    │      └── 劇団A
    │             └── Role = 管理者
    │
    └── Membership
           └── 劇団B
                  └── Role = キャスト

---

# Membership

Membershipは、
PersonとOrganizationの所属関係を表す。

Membershipによって、

- Organizationへの所属
- Organization内のRole
- Organization内での権限

などを管理する。

一つのMembershipは、
基本的に一つのRoleを参照する。

同じPersonであっても、
OrganizationごとのMembershipによって
異なるRoleを持つことができる。

Membershipの詳細な管理ルールは
Membership Domainで定義する。

---

# Participant

PersonはProductionへ参加できる。

Productionとの関係はParticipantによって管理する。

PersonはParticipantを直接保持するものではなく、
ParticipantのSubjectとして参照される。

基本構造：

Production
    │
    └── Participant
            │
            └── Subject
                   │
                   └── Person

Participantによって、

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- その他の参加区分

などのProductionへの参加を表現する。

Participantの詳細な管理ルールは
Participant Domainで定義する。

---

# Reservation

Personは観客として
Productionのチケットを予約できる。

Reservationは、
観客による予約というFactを表す。

基本構造：

Person
    │
    └── Reservation
            │
            └── Performance

ReservationはPersonの子Entityとして
直接保持するのではなく、
ReservationのBookerとしてPersonを参照する。

---

# Check In

Check Inは、
観客が実際に公演へ来場したFactを表す。

Reservationが存在しても、
Check Inされるまでは観劇実績として扱わない。

基本Flow：

Reservation
    ↓
Issued Ticket
    ↓
Check In
    ↓
CheckInCompleted
    ↓
History

CheckInCompletedを契機として、
Audience Historyが生成される。

Check Inの詳細な管理ルールは
Check In Domainで定義する。

---

# Contact Information

Personに必要なContact Informationを
管理できる。

Contact Informationは、
Profileの公開情報とは区別する。

例えば、

- Email
- Phone
- その他連絡先

など。

公開プロフィールとして公開する情報と、
Business上の連絡先を混同しない。

具体的なContact Informationの管理ルールは、
Contact Domainで定義する。

---

# Public Settings

Personに関連する
公開設定を管理できる。

ProfileやHistoricalActivityなどの
公開範囲に利用できる。

具体的な公開設定ルールは、
Profile / Public Settings Domainで定義する。

---

# Status

PersonStatusは人物の状態を表す。

例：

- ACTIVE
- INACTIVE

論理削除が必要な場合は
Statusによって管理する。

PersonIdそのものは変更・再利用しない。

---

# Deletion

Personを物理削除することによって、
過去のBusiness Factを破壊しない。

Personの利用停止が必要な場合は、
Statusによって管理する。

Personに関連する、

- Membership
- Participant
- Reservation
- History

などの過去Factを、
PersonのStatus変更によって削除しない。

---

# Privacy

Personは個人情報を含むため、
アクセス制御を適切に行う。

特に、

- Contact Information
- Reservation
- Audience History

などは、
公開Profile情報とは区別する。

Person本人が参照できる情報と、
Organization内部で参照できる情報、
一般公開できる情報を分離する。

---

# Multi Tenant

PersonはOrganizationとは独立したIdentityである。

そのため、
一人のPersonが複数Organizationに所属できる。

ただし、
Organization内部の情報については
MembershipおよびRoleによる権限管理に従う。

Personが複数Organizationに所属している場合でも、
Organizationごとの情報を混在させない。

---

# Audit

Personには、
変更を追跡できるよう
監査情報を保持する。

基本情報：

- Created At
- Updated At

必要に応じて、

- Created By
- Updated By

などを保持する。

ProfileやHistoricalActivityの変更については、
それぞれのDomainの監査情報を使用する。

---

# Business Rules

PersonはStageArt上の個人Business Identityを表す。

PersonはUserAccountとは独立して存在する。

Personは複数のOrganizationへ所属できる。

Organizationへの所属はMembershipで管理する。

一つのMembershipは、
基本的に一つのRoleを参照する。

PersonはProductionへ参加できる。

Productionへの参加はParticipantで管理する。

Personは観客としてReservationのBookerになれる。

Reservationの予約情報をPerson自身が直接保持するものではない。

一般観客はStageArtのInternal Portalへ参加する必要がない。

一般観客についてPersonを必須作成しない。

StageArtユーザーとして登録した観客はPersonとして管理できる。

登録された観客は自身の観劇履歴を確認できる。

ProfileはPerson本人が作成・編集できる。

ProfileはPersonごとに一つとする。

HistoricalActivityはProfileの子Entityである。

HistoricalActivityは本人申告の過去活動実績である。

HistoricalActivityはHistoryとは別Entityである。

StageArt上で発生した活動FactはHistoryで管理する。

PersonはHistoryを直接作成・編集しない。

Participantから生成されるParticipation HistoryはHistory Domainで管理する。

CheckInCompletedから生成されるAudience HistoryはHistory Domainで管理する。

Audience HistoryのSubjectはReservation.Bookerを基本とする。

HandledParticipantはAudience HistoryのSubjectにならない。

CreatedByはAudience HistoryのSubjectにならない。

UpdatedByはAudience HistoryのSubjectにならない。

Companion Domainは存在しない。

PersonのStatus変更によって過去のBusiness Factを削除しない。

---

# Domain Events

Personに直接関連する主なDomain Event：

- PersonCreated
- PersonProfileUpdated
- PersonArchived

Personに関連するBusiness Eventとして、
以下のEventが存在する。

Participant Domain：

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

Check In Domain：

- CheckInCompleted

Historyの生成・更新は、
これらのBusiness Eventを契機として
History Domainが処理する。

Person Domain自身がHistoryを直接生成・更新しない。

---

# Design Decisions

Personは人物を表すBusiness Domainである。

PersonはUserAccountとは独立したDomainである。

UserAccountは認証Identity、
PersonはBusiness Identityを表す。

PersonとOrganizationの関係はMembershipで管理する。

PersonとProductionの関係はParticipantで管理する。

PersonはReservationのBookerとなることができる。

ProfileはPersonに1つだけ存在し、
本人が作成・編集できる。

Profileには、
本人が入力するプロフィール情報を保持する。

本人が入力する過去の活動実績は、
Profileの子EntityであるHistoricalActivityとして管理する。

HistoricalActivityは、
StageArt上で確認された正式な活動履歴ではない。

StageArt上で発生した活動Factは、
History DomainによってHistoryとして管理する。

したがって、

本人申告の過去実績：

Person
    ↓
Profile
    ↓
HistoricalActivity


StageArt上の正式な活動履歴：

Participant / Check In
    ↓
Domain Event
    ↓
History

という責務分離を行う。

HistoryはPersonの子Entityではなく、
独立Domainとして管理する。

一般観客はPersonを必須とせず、
StageArtユーザーとして登録した観客のみ
Personとして管理できる。

登録ユーザーの観劇履歴は、
Reservation.BookerとCheckInCompletedをもとに
History Domainから取得する。

Companion Domainは設けない。

---

# Design Principles

- Personは個人Business Identityを表す。
- PersonはUserAccountとは独立する。
- UserAccountは認証Identity、PersonはBusiness Identityを表す。
- PersonはBusiness Activityの主体である。
- PersonとOrganizationの関係はMembershipで管理する。
- 一つのMembershipは基本的に一つのRoleを参照する。
- PersonとProductionの関係はParticipantで管理する。
- PersonはReservationのBookerになれる。
- ProfileはPerson本人が作成・編集できる。
- ProfileはPersonごとに一つとする。
- HistoricalActivityはProfileの子Entityである。
- HistoricalActivityは本人申告の過去実績である。
- HistoricalActivityとHistoryを混同しない。
- StageArt上の活動FactはHistoryで管理する。
- HistoryはPersonの子Entityではない。
- Historyは独立Domainとして管理する。
- ParticipantからParticipation Historyを生成できる。
- CheckInCompletedからAudience Historyを生成できる。
- Audience HistoryのSubjectはReservation.Bookerである。
- HandledParticipantはAudience HistoryのSubjectにならない。
- CreatedByはAudience HistoryのSubjectにならない。
- UpdatedByはAudience HistoryのSubjectにならない。
- 一般観客はPersonを必須としない。
- StageArtユーザーとして登録した観客は自身の観劇履歴を確認できる。
- Companionは管理しない。
- PersonのStatus変更によって過去Factを削除しない。
- ProfileとMembershipを分離する。
- ProfileとParticipantを分離する。
- ProfileとHistoryを分離する。
- HistoricalActivityとHistoryを分離する。
- Blueprintを唯一の設計基準とする。