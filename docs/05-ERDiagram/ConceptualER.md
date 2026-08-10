# StageArt Blueprint
# Conceptual ER Diagram

Version : 4.0

---

# Purpose

Conceptual ER Diagramは、StageArtを構成する主要なDomain同士の関係を表現する。

実装やデータベース構造ではなく、
Business上の概念と関係性を示す。

---

# Conceptual Model

    erDiagram

        Account ||--o| Person : authenticates

        Person ||--o{ Membership : belongs_to
        Organization ||--o{ Membership : has

        Organization ||--o{ ExternalConnection : has
        ExternalConnection }o--|| Service : uses
        ExternalConnection ||--|| Credential : has

        Organization ||--o{ Project : owns
        Project ||--|| Production : manages

        Production ||--|| Person : primary_manager
        Production ||--o{ ProductionDelegate : has
        ProductionDelegate }o--|| Person : assigned_to
        ProductionDelegate }o--|| DelegateRole : has

        Production ||--o{ Performance : has
        Production ||--o{ Participant : has

        Participant }o--|| Subject : represents
        Subject ||--o| Person : refers_to
        Subject ||--o| Organization : refers_to

        Performance ||--o{ Reservation : accepts

        Reservation }o--o| Participant : handled_by
        Reservation ||--o{ Companion : has
        Reservation ||--o{ ReservationSeat : has

        Subject ||--o{ History : has

        Production ||--o{ History : relates_to
        Performance ||--o{ History : relates_to

---

# Relationship Definitions

## Account - Person

    Account
        ↓
    Person

Accountは認証情報を表す。

PersonはBusiness上の人物を表す。

AccountとPersonは独立した概念であり、
Accountは認証用途、
PersonはBusiness用途で利用する。

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
        └── Google Drive

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
        ▼
    Project

Organizationは複数のProjectを管理できる。

Projectは制作活動を管理するInternal Domainである。

---

## Project - Production

    Project
        │
        ▼
    Production

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

PrimaryManagerはProductionの管理責任者を表す。

PrimaryManagerはPersonを参照する。

PrimaryManagerはProductionに対する全権限を持つ。

PrimaryManagerはDelegateRoleによって権限を制限されない。

---

## Production - ProductionDelegate

    Production
        │
        ├── ProductionDelegate
        ├── ProductionDelegate
        └── ProductionDelegate

Productionは複数のProductionDelegateを持つことができる。

ProductionDelegateは、
PrimaryManagerからProductionの管理権限を委任されたPersonを表す。

ProductionDelegateはProductionの子Entityとして管理する。

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
Productionごとに異なるDelegateRoleを設定できる。

---

## ProductionDelegate - DelegateRole

    ProductionDelegate
        │
        ▼
    DelegateRole

ProductionDelegateは一つのDelegateRoleを参照する。

DelegateRoleは、
ProductionDelegateに付与する権限セットを表す。

DelegateRoleはPersonに直接紐付かない。

ProductionDelegateを介して、
特定ProductionにおけるPersonの権限を定義する。

---

## DelegateRole - Permission

    DelegateRole
        │
        ▼
    Permission Set

DelegateRoleは、
あらかじめ定義された権限セットを持つ。

DelegateRoleはProductionDelegateへ
定義済みの権限を付与する。

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

Historyは必ず一つのProductionに関連する。

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
StageArtが外部連携するサービスを共通の構造で扱う。

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
                └── DelegateRole

PrimaryManagerはProductionに対する全権限を持つ。

ProductionDelegateはDelegateRoleによって
あらかじめ定義された権限のみを持つ。

DelegateRoleはProduction単位で適用される。

同一Personが複数ProductionのDelegateになる場合、
Productionごとに異なるDelegateRoleを持つことができる。

---

# Domain Boundaries

Conceptual ERでは、
Domain間のBusiness上の関係のみを表現する。

以下の関係は特に重要である。

    Participant
        ↓
    Subject

    Reservation
        ↓
    HandledParticipant
        ↓
    Participant

    Subject
        ↓
    History

    Organization
        ↓
    ExternalConnection
        ├── Service
        └── Credential

    Production
        ├── PrimaryManager
        │       └── Person
        │
        └── ProductionDelegate
                ├── Person
                └── DelegateRole

HistoryはParticipantやReservationの子Entityではない。

HistoryはDomain Eventを契機として
独立して生成・更新される。

ExternalConnectionはOrganizationの子Entityである。

CredentialはExternalConnectionの子Entityである。

ServiceはMaster Domainとして管理され、
複数のExternalConnectionから参照される。

ProductionDelegateはProductionの子Entityである。

PrimaryManagerはProductionからPersonを参照する。

DelegateRoleはProductionDelegateに付与される
権限セットを表す。

---

# Design Principles

- Conceptual ERはBusiness上の関係を表現する。
- Databaseの物理構造は表現しない。
- Foreign KeyはLogical ERで定義する。
- ParticipantはSubjectを介して活動主体を参照する。
- SubjectはPersonまたはOrganizationを表す。
- Reservationは任意のHandledParticipantを持つ。
- HandledParticipantはReservationとParticipantのBusiness上の関係を表す。
- CompanionはReservationに属する。
- ReservationSeatはReservationに属する。
- Historyは独立したDomainである。
- HistoryはSubjectを介してPersonまたはOrganizationと関連する。
- HistoryはProductionを必ず参照する。
- PerformanceはHistoryに対して任意である。
- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionは外部サービスとの接続を表す。
- ExternalConnectionはSNSに限定しない。
- Serviceは外部サービスの種類を表すMaster Domainである。
- Serviceは複数のExternalConnectionから参照できる。
- CredentialはExternalConnectionに属する。
- Credentialは単独のPublic Resourceではない。
- Credentialは外部サービスへの認証情報を表す。
- Credentialは平文で保存しない。
- ExternalConnectionはServiceを参照する。
- 外部サービス固有のAPI仕様はConceptual ERでは表現しない。
- Productionは一人のPrimaryManagerを持つ。
- PrimaryManagerはPersonを参照する。
- PrimaryManagerはProductionに対する全権限を持つ。
- Productionは複数のProductionDelegateを持つことができる。
- ProductionDelegateはProductionの子Entityである。
- ProductionDelegateはPersonを参照する。
- ProductionDelegateはDelegateRoleを参照する。
- DelegateRoleはあらかじめ定義された権限セットを表す。
- DelegateRoleはProduction単位で適用される。
- 同一Personが複数Productionで異なるDelegateRoleを持つことを許可する。
- Organization MembershipとProduction単位の管理権限を分離する。
- PrimaryManagerはDelegateRoleによる制限を受けない。
- ProductionDelegateはDelegateRoleによって権限を制限する。
