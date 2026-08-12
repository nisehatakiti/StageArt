# StageArt Blueprint

# Conceptual ER Diagram

Version : 4.3

---

# Purpose

Conceptual ER Diagramは、StageArtを構成する主要なDomain同士の関係を表現する。

実装やデータベース構造ではなく、
Business上の概念と関係性を示す。

---

# Conceptual Model

    erDiagram

        UserAccount ||--o| Person : authenticates

        Person ||--o{ Membership : belongs_to
        Organization ||--o{ Membership : has
        Membership }o--|| Role : applies

        Organization ||--o{ ExternalConnection : has
        ExternalConnection }o--|| Service : uses
        ExternalConnection ||--|| Credential : has

        Organization ||--o{ Project : owns
        Project ||--|{ Production : has

        Production }o--|| Person : primary_manager
        Production ||--o{ ProductionDelegate : has
        ProductionDelegate }o--|| Person : assigned_to
        ProductionDelegate }o--|| Role : applies

        Production ||--o{ Performance : has
        Production ||--o{ Participant : has

        Participant }o--|| Subject : represents
        Subject ||--o| Person : refers_to
        Subject ||--o| Organization : refers_to

        Production ||--o{ Rehearsal : has
        Rehearsal ||--o{ RehearsalAttendance : has
        RehearsalAttendance }o--|| Person : attends

        Rehearsal ||--o| Timetable : has

        Performance ||--o{ Reservation : accepts

        Reservation }o--o| Participant : handled_by
        Reservation ||--o{ Companion : has
        Reservation ||--o{ ReservationSeat : has

        Subject ||--o{ History : has
        Production ||--o{ History : relates_to
        Performance ||--o{ History : relates_to

---

# Relationship Definitions

## UserAccount - Person

    UserAccount
        ↓
    Person

UserAccountはAuthentication Identityを表す。

PersonはBusiness上の人物を表す。

UserAccountとPersonは独立した概念であり、
UserAccountはAuthentication用途、
PersonはBusiness用途で利用する。

PersonはUserAccountを持たなくてもよい。

UserAccountはOrganizationやProductionに直接所属しない。

---

## Person - Membership - Organization

    Person
        │
        ▼
    Membership
        ▲
        │
    Organization

MembershipはPersonとOrganizationの所属関係を表す。

PersonとOrganizationはMembershipを介して関連付けられる。

Personは複数のOrganizationに所属できる。

Organizationは複数のPersonをMembershipによって保持できる。

---

## Membership - Role

    Person
        │
        ▼
    Membership
        │
        ▼
      Role
        │
        ▼
    Permission

Membershipに関連するRoleは、
Organization ScopeでPersonに適用される。

一つのMembershipは、
基本的に一つのRoleを参照する。

同じPersonであっても、
OrganizationごとのMembershipによって
異なるRoleを持つことができる。

RoleはPermission Setを定義する。

Role自体はOrganization ScopeやProduction Scopeを保持しない。

RoleをどのScopeで誰に適用するかは、
MembershipまたはProductionDelegateとの関係によって表現する。

RoleAssignmentという独立したDomain Entityは作成しない。

---

## Organization - ExternalConnection

    Organization
        │
        ├── ExternalConnection
        ├── ExternalConnection
        └── ExternalConnection

一つのOrganizationは複数のExternalConnectionを持つことができる。

ExternalConnectionは、
Organizationと外部サービスとの接続関係を表す。

ExternalConnectionはOrganizationの子Entityである。

---

## ExternalConnection - Service

    ExternalConnection
        │
        ▼
      Service

ExternalConnectionは一つのServiceを参照する。

Serviceは外部サービスの種類を表すMaster Domainである。

例えば、

    Service
        ├── X
        ├── Instagram
        ├── Facebook
        ├── YouTube
        ├── TikTok
        ├── LINE
        ├── Google
        ├── Google Drive
        └── Google Calendar

などを表現する。

SNSはServiceの一種として扱う。

SNS専用のDomainは作成しない。

一つのServiceは複数のOrganizationから参照される。

---

## ExternalConnection - Credential

    ExternalConnection
        │
        ▼
    Credential

ExternalConnectionは、
外部サービスへの接続に必要なCredentialを保持する。

CredentialはExternalConnectionに属する。

Credentialは単独のBusiness Resourceとして扱わない。

Credentialには、

- OAuth
- Access Token
- Refresh Token
- API Key
- Secret

などの認証情報を扱える構造を持たせる。

認証情報は平文で保存しない。

---

## Organization - Project

    Organization
        │
        ├── Project
        ├── Project
        └── Project

Organizationは複数のProjectを管理できる。

Projectは制作活動を管理するInternal Domainである。

---

## Project - Production

    Project
        │
        ├── Production
        ├── Production
        └── Production

一つのProjectは一つ以上のProductionを持つことができる。

ProjectはProductionの制作活動を管理する。

Productionは利用者・観客へ公開されるBusiness Resourceである。

Projectは公開APIには出さない。

---

## Production - PrimaryManager

    Production
        │
        ▼
    PrimaryManager
        │
        ▼
      Person

Productionは一人のPrimaryManagerを持つ。

一人のPersonは、
複数のProductionのPrimaryManagerになることができる。

PrimaryManagerはProductionの管理責任者を表す。

PrimaryManagerはPersonを参照する。

PrimaryManagerはProductionに対する全権限を持つ。

PrimaryManagerはProductionDelegateによって制限される権限ではない。

---

## Production - ProductionDelegate

    Production
        │
        ├── ProductionDelegate
        ├── ProductionDelegate
        └── ProductionDelegate

Productionは複数のProductionDelegateを持つことができる。

ProductionDelegateは、
Production ScopeにおいてPersonへRoleを適用する関係を表す。

ProductionDelegateはProductionの子Entityとして管理する。

ProductionDelegateはPrimaryManagerと異なり、
適用されたRoleに定義されたPermissionのみを持つ。

---

## ProductionDelegate - Person

    ProductionDelegate
        │
        ▼
      Person

ProductionDelegateは一人のPersonを参照する。

同一Personを複数のProductionに
ProductionDelegateとして登録できる。

また、同一Personであっても、
Productionごとに異なるRoleを適用できる。

---

## ProductionDelegate - Role

    ProductionDelegate
        │
        ▼
       Role

ProductionDelegateは一つのRoleを参照する。

Roleは、
ProductionDelegateに適用するPermission Setを定義する。

RoleはPersonに直接紐付かない。

ProductionDelegateを介して、
特定ProductionにおけるPersonへRoleを適用する。

RoleそのものはProduction専用ではない。

Organization ScopeとProduction Scopeの
両方で同じRole Definitionを利用できる。

---

## Role - Permission

    Role
        │
        ▼
    Permission Set
        │
        ▼
    Permission

Roleは、
あらかじめ定義されたPermission Setを持つ。

RoleはMembershipまたはProductionDelegateを介して、
Personへ適用される。

Permissionの具体的な構造はAuthorization設計で定義する。

---

## Production - Performance

    Production
        │
        ├── Performance
        ├── Performance
        └── Performance

一つのProductionは複数のPerformanceを持つ。

Performanceは実際の公演回を表す。

---

## Production - Participant

    Production
        │
        ├── Participant
        ├── Participant
        └── Participant

一つのProductionは複数のParticipantを持つ。

ParticipantはProductionへの参加を表す。

---

## Participant - Subject

    Participant
          │
          ▼
       Subject

Participantは活動主体を直接PersonまたはOrganizationとして保持しない。

Subjectを介して活動主体を参照する。

Subjectは以下を表現できる。

    Subject
       ├── Person
       └── Organization

これにより、
PersonとOrganizationの双方を
同じParticipant構造で扱う。

---

## Production - Rehearsal

    Production
        │
        ├── Rehearsal
        ├── Rehearsal
        └── Rehearsal

一つのProductionは複数のRehearsalを持つ。

RehearsalはProductionに所属する
稽古・活動予定を表す。

稽古予定と確定稽古を、
別Entityまたは別Domainとして分離しない。

Rehearsal自身のStatusによって、
現在のLifecycleを表現する。

Rehearsalは、

- DRAFT
- SCHEDULED
- CONFIRMED
- ACTIVE
- COMPLETED
- CANCELLED

などの状態を持つ。

---

## Rehearsal - RehearsalAttendance

    Rehearsal
        │
        ├── RehearsalAttendance
        ├── RehearsalAttendance
        └── RehearsalAttendance

一つのRehearsalは複数のRehearsalAttendanceを持つ。

RehearsalAttendanceは、
そのRehearsalに対するPersonの参加状態を表す。

RehearsalAttendanceは、
Rehearsalの子Entityとして管理する。

RehearsalAttendanceを
独立したDomain Entityとして扱わない。

---

## RehearsalAttendance - Person

    RehearsalAttendance
        │
        ▼
      Person

RehearsalAttendanceは一人のPersonを参照する。

同一Personは、
複数のRehearsalに対して
RehearsalAttendanceを持つことができる。

同一Rehearsalにおける
一人のPersonの参加状態は、
一つのRehearsalAttendanceで管理する。

---

## Rehearsal Attendance Status

RehearsalAttendanceは、
Rehearsalの予定段階から存在できる。

予定確認段階では、

- UNANSWERED
- ATTENDING
- NOT_ATTENDING

などの状態を持つ。

RehearsalがACTIVEになった場合、
同じRehearsalAttendanceのStatusを
実際の出欠状態へ変更する。

例えば、

    ATTENDING
        ↓
    ATTENDED

    ATTENDING
        ↓
    LATE

    ATTENDING
        ↓
    ABSENT

など。

予定段階のRehearsalAttendanceを削除して、
別の出欠Entityを作成することはしない。

---

## RehearsalAttendance Retention

RehearsalがCONFIRMEDになっても、
RehearsalAttendanceは保持する。

RehearsalがACTIVEになっても、
RehearsalAttendanceは保持する。

RehearsalがCOMPLETEDになった後も、
RehearsalAttendanceは保持する。

これにより、

- 参加予定者
- 実際の参加者
- 欠席者
- 遅刻者

などを同じRehearsalAttendanceから参照できる。

---

## Rehearsal - Participant

    Production
        │
        ├── Participant
        │
        └── Rehearsal
                │
                └── RehearsalAttendance
                        │
                        ▼
                      Person

RehearsalはParticipantを直接所有しない。

RehearsalAttendanceの対象者は、
ProductionのParticipantを基本とする。

ただし、
Production Participant全員が
すべてのRehearsalへ参加するとは限らない。

Rehearsalごとに、
参加予定者を設定できる。

---

## Rehearsal - Timetable

    Rehearsal
        │
        ▼
    Timetable
        │
        ▼
    Timetable Item

Rehearsal内の詳細な時間割・進行は、
Timetable Domainで管理する。

Rehearsalは稽古そのものの予定を管理する。

Timetableは、
そのRehearsal内の詳細な進行を管理する。

TimetableはRehearsalそのもののLifecycleを
管理しない。

---

## Rehearsal - Google Calendar

    Rehearsal
        │
        ▼
    External Calendar Event
        │
        ▼
    Google Calendar

CONFIRMEDとなったRehearsalは、
Google Calendarへ連携できる。

Google Calendar Eventは、
Rehearsalそのものではない。

StageArt上のRehearsalを正本とする。

Google CalendarへのAPI呼び出しは、
Infrastructure Layerが担当する。

---

## Rehearsal - Calendar Target

Google Calendarへの登録対象は、
RehearsalAttendanceと完全には一致しない。

Rehearsalの共有対象となるPersonを、
Calendar連携対象として指定できる。

したがって、

    RehearsalAttendance
        Status = NOT_ATTENDING

であるPersonであっても、
必要に応じてCalendarへ予定を登録できる。

Calendar登録対象と
稽古参加予定は別の概念として扱う。

---

## Performance - Reservation

    Performance
        │
        ├── Reservation
        ├── Reservation
        └── Reservation

一つのPerformanceは複数のReservationを受け付ける。

ReservationはPerformanceに対する予約を表す。

---

## Reservation - HandledParticipant

    Reservation
          │
          ▼
    HandledParticipant
          │
          ▼
      Participant

Reservationは任意でHandledParticipantを持つ。

HandledParticipantは、
その予約における「扱い」のParticipantを表す。

いわゆる「○○扱い」の予約を表現する。

HandledParticipantが指定されない予約も存在する。

HandledParticipantはReservationとParticipantの
Business上の関係を表現するものであり、
独立したDomain Entityではない。

---

## Reservation - Companion

    Reservation
        │
        ├── Companion
        ├── Companion
        └── Companion

一つのReservationは複数のCompanionを持つことができる。

Companionは同行者を表す。

CompanionはReservationに属する子Entityであり、
単独では存在しない。

---

## Reservation - ReservationSeat

    Reservation
        │
        ├── ReservationSeat
        ├── ReservationSeat
        └── ReservationSeat

ReservationSeatはReservationに属する。

ReservationSeatは予約された座席を表す。

---

## Subject - History

    Subject
        │
        ├── History
        ├── History
        └── History

Subjectは複数のHistoryを持つ。

HistoryはSubjectがStageArt上で行った活動を記録する。

HistoryはPersonやOrganizationの子Entityではなく、
独立したDomainとして管理する。

---

## History - Production

    History
        │
        ▼
    Production

HistoryはProductionに関連付けることができる。

Productionによって、
どの公演・作品に関する活動だったかを識別する。

---

## History - Performance

    History
        │
        ▼
    Performance

Historyは必要に応じてPerformanceに関連する。

Production単位の活動ではPerformanceを持たない。

特定の公演回に紐付く活動ではPerformanceを持つ。

---

# Accounting Concept

AccountはAccounting Domainに属する
会計上の勘定科目を表す。

AccountはAuthentication Identityではない。

Authentication IdentityはUserAccountによって表現する。

したがって、

    UserAccount
        ↓
      Person

と、

    Organization
        ↓
    Accounting
        ↓
      Account

は完全に別の概念として管理する。

AccountとUserAccountを統合しない。

---

# External Connection Concept

ExternalConnectionは、
Organizationが外部サービスを利用するための接続関係を表す。

    Organization
        │
        ▼
    ExternalConnection
        ├── Service
        └── Credential

ExternalConnectionはSNS専用ではない。

SNS、動画サービス、クラウドサービス、
メッセージングサービスなど、
StageArtが外部連携するサービスを
共通の構造で扱う。

Serviceは外部サービスの種類を表す。

Credentialは外部サービスへの認証情報を表す。

---

# External Connection Boundary

ExternalConnectionは、
外部サービスとの接続境界として扱う。

    Organization
        ↓
    ExternalConnection
        ↓
    Service
        ↓
    Infrastructure Adapter
        ↓
    External Service

Domain Modelは、
外部サービス固有のAPI仕様を直接扱わない。

外部サービスごとの差異はInfrastructure Layerで吸収する。

---

# Credential Boundary

Credentialは、
ExternalConnectionに属する認証情報である。

Credentialは単独のPublic Resourceではない。

認証情報は、

- API Response
- Domain Event
- Log
- Audit Log
- Error Message

へ公開してはならない。

具体的な暗号化およびSecret Storageは、
Infrastructure Layerで管理する。

---

# Organization Role Boundary

Organizationの管理権限は、
MembershipとRoleによって管理する。

    Person
        ↓
    Membership
        ↓
    Organization
        ↓
       Role
        ↓
    Permission

MembershipはOrganizationへの所属関係を表す。

RoleはPermission Setを定義する。

RoleAssignmentという独立Domainは作成しない。

---

# Production Management Boundary

Productionの管理権限は、
Organization Membershipとは別に管理する。

Productionは、
PrimaryManagerとProductionDelegateによって
Production単位の管理権限を管理する。

    Production
        ├── PrimaryManager
        │       └── Person
        │
        └── ProductionDelegate
                ├── Person
                └── Role

PrimaryManagerはProductionに対する全権限を持つ。

一人のPersonは、
複数のProductionのPrimaryManagerになることができる。

ProductionDelegateはRoleによって
あらかじめ定義されたPermissionのみを持つ。

RoleはProduction Scopeで適用される。

同一Personが複数ProductionのDelegateになる場合、
Productionごとに異なるRoleを適用できる。

---

# Rehearsal Boundary

RehearsalはProduction Scopeに属する。

    Organization
        ↓
    Project
        ↓
    Production
        ↓
    Rehearsal
        ↓
    RehearsalAttendance
        ↓
    Person

RehearsalのStatusによって、
稽古予定から実施完了までのLifecycleを表現する。

稽古予定と確定稽古を別Entityとして管理しない。

RehearsalCandidateは作成しない。

RehearsalAvailabilityは作成しない。

RehearsalAttendanceは、
Rehearsalに属する子Entityとして管理する。

---

# Rehearsal Attendance Boundary

RehearsalAttendanceは、
予定確認と実際の出欠を一つの関係として管理する。

    Rehearsal
        ↓
    RehearsalAttendance
        ↓
      Person

予定確認時：

    UNANSWERED
    ATTENDING
    NOT_ATTENDING

実施時：

    ATTENDED
    LATE
    ABSENT

RehearsalがACTIVEになっても、
RehearsalAttendanceそのものは削除しない。

Statusだけを変更する。

---

# Domain Boundaries

Conceptual ERでは、
Domain間のBusiness上の関係のみを表現する。

以下の関係は特に重要である。

    UserAccount
        ↓
      Person

    Person
        ↓
    Membership
        ↓
    Organization
        ↓
       Role
        ↓
    Permission

    Organization
        ↓
    Project
        ↓
    Production

    Production
        ├── PrimaryManager
        │       └── Person
        │
        └── ProductionDelegate
                ├── Person
                └── Role
                        ↓
                    Permission

    Production
        ↓
    Participant
        ↓
      Subject
        ├── Person
        └── Organization

    Production
        ↓
    Rehearsal
        ↓
    RehearsalAttendance
        ↓
      Person

    Rehearsal
        ↓
    Timetable
        ↓
    Timetable Item

    Production
        ↓
    Performance
        ↓
    Reservation

    Reservation
        ↓
    HandledParticipant
        ↓
    Participant

    Organization
        ↓
    ExternalConnection
        ├── Service
        └── Credential

    Subject
        ↓
    History

    Production
        ↓
    History

    Performance
        ↓
    History

---

# Design Decisions

Conceptual ERは、
Business上の主要なDomain関係を表現する。

Database TableやColumnなどの
Physical設計は表現しない。

OrganizationはStageArtにおけるTenantである。

PersonとOrganizationはMembershipによって関連付ける。

Membershipに適用するRoleによって、
Organization ScopeのPermissionを決定する。

RoleAssignmentという独立Domainは作成しない。

DelegateRoleという別のRole体系は使用しない。

Production Scopeでは、
ProductionDelegateを介してRoleを適用する。

PrimaryManagerはProductionの管理責任者であり、
Productionに対する全権限を持つ。

一人のPersonは、
複数ProductionのPrimaryManagerになることができる。

Organization ScopeとProduction Scopeでは、
同じRole Definitionを利用できる。

OrganizationはProjectを持つ。

Projectは一つ以上のProductionを持つ。

ProductionはPerformanceを持つ。

ProductionはParticipantを持つ。

ParticipantはSubjectを介して
PersonまたはOrganizationを参照する。

ProductionはRehearsalを持つ。

RehearsalはStatusによってLifecycleを管理する。

稽古予定と確定稽古を別Entityとして管理しない。

RehearsalCandidateは作成しない。

RehearsalAvailabilityは作成しない。

RehearsalはRehearsalAttendanceを持つ。

RehearsalAttendanceはRehearsalの子Entityである。

RehearsalAttendanceは、
予定確認と実際の出欠を同じEntityで管理する。

RehearsalがACTIVEになっても、
RehearsalAttendanceを削除しない。

RehearsalAttendanceのStatusを変更して
実際の出欠を記録する。

同一Rehearsalにおける
同一Personの参加状態は、
一つのRehearsalAttendanceで管理する。

RehearsalはTimetableを持つ。

TimetableはRehearsal内の詳細な時間割・進行を管理する。

Google Calendar EventはRehearsalの正本ではない。

StageArt上のRehearsalを正本とする。

ReservationはPerformanceに属する。

Reservationは任意でHandledParticipantを持つ。

ReservationはCompanionを持つ。

ReservationはReservationSeatを持つ。

Historyは独立Domainとして管理する。

AccountはAccounting Domainに属する会計上の勘定科目である。

AccountとUserAccountは完全に別の概念として管理する。

ExternalConnectionはOrganizationの子Entityである。

ExternalConnectionはSNS専用ではない。

Serviceによって外部サービスの種類を識別する。

CredentialはExternalConnectionに属する。

Credentialは平文で保存しない。

外部サービス固有のAPI仕様はInfrastructure Layerで扱う。

---

# Design Principles

- Conceptual ERはBusiness上の概念と関係性を表現する。
- Database TableやColumnなどのPhysical設計はConceptual ERで表現しない。
- OrganizationはTenantである。
- PersonとOrganizationはMembershipによって関連付ける。
- MembershipはOrganizationへの所属関係を表す。
- MembershipはRoleを介してOrganization ScopeのPermissionを得る。
- 一つのMembershipは基本的に一つのRoleを参照する。
- RoleはPermission Setを定義する。
- RoleはScopeを保持しない。
- RoleAssignmentという独立Domainを作成しない。
- DelegateRoleという別のRole体系を使用しない。
- ProductionDelegateはProduction ScopeでRoleを適用する。
- ProductionDelegateは独自のPermissionを定義しない。
- PrimaryManagerはProductionに対する全権限を持つ。
- 一人のPersonは複数ProductionのPrimaryManagerになれる。
- OrganizationとProductionで同じRole Definitionを利用できる。
- OrganizationはProjectを持つ。
- Projectは一つ以上のProductionを持つ。
- ProductionはPerformanceを持つ。
- ProductionはParticipantを持つ。
- ParticipantはSubjectを介してPersonまたはOrganizationを参照する。
- ProductionはRehearsalを持つ。
- RehearsalのLifecycleはStatusで管理する。
- RehearsalCandidateを作成しない。
- RehearsalAvailabilityを作成しない。
- RehearsalはRehearsalAttendanceを持つ。
- RehearsalAttendanceはRehearsalの子Entityである。
- RehearsalAttendanceは予定確認と実際の出欠を一つのEntityで管理する。
- RehearsalがACTIVEになってもRehearsalAttendanceを削除しない。
- RehearsalAttendanceのStatus変更によって実際の出欠を記録する。
- 同一Rehearsal × PersonのAttendanceは一つで管理する。
- RehearsalはTimetableを持つ。
- TimetableはRehearsal内の詳細な時間割を管理する。
- Google Calendar EventはRehearsalの正本ではない。
- StageArt上のRehearsalを正本とする。
- PerformanceはReservationを受け付ける。
- ReservationはCompanionを持つ。
- ReservationはReservationSeatを持つ。
- ReservationはHandledParticipantを任意で持つ。
- Historyは独立Domainとして管理する。
- AccountはAccounting Domainの勘定科目である。
- AccountとUserAccountを統合しない。
- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionはSNSに限定しない。
- Serviceは外部サービスの種類を表す。
- CredentialはExternalConnectionに属する。
- Credentialは平文保存しない。
- 外部サービス固有のAPI処理はInfrastructure Layerで実装する。
- Blueprintを唯一の設計基準とする。