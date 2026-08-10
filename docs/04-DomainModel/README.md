# StageArt Blueprint
# Domain Model

Version : 2.0

---

# Purpose

Domain Modelは、
StageArtにおけるBusiness Domainの構造を定義する。

Domain Modelは、
UI、API、Database、WordPressなどの
Infrastructureから独立して設計する。

Business RuleはDomain Layerが管理する。

---

# Domain Structure

StageArtの主要Domainは以下で構成する。

    Organization
    ├── Membership
    ├── ExternalConnection
    │      ├── Service
    │      └── Credential
    │
    └── Project

    Project
    └── Production

    Production
    ├── Performance
    ├── Participant
    └── Reservation

    Person
    └── History

各Domainは、
それぞれの責務とBusiness Ruleを持つ。

---

# Organization

Organizationは、
StageArtにおけるTenantである。

舞台芸術活動を行う団体を表す。

Organizationは、

- 劇団
- プロデュース団体
- ダンスカンパニー
- 学生劇団
- 演劇サークル
- 実行委員会

などを表現できる。

Organizationは、
Business DataのTenant境界となる。

---

# Membership

Membershipは、
PersonとOrganizationの所属関係を表す。

    Person
       ↓
    Membership
       ↓
    Organization

Personは複数Organizationへ所属できる。

Organizationは、
Membershipを通じてPersonとの関係を管理する。

Organization自身は、
MemberやRoleを直接保持しない。

---

# ExternalConnection

ExternalConnectionは、
Organizationと外部サービスとの接続関係を表す。

    Organization
    └── ExternalConnection
           ├── Service
           └── Credential

ExternalConnectionはSNS専用ではない。

SNS、動画サービス、
クラウドサービス、
メッセージングサービスなど、
StageArtが外部連携するサービスを
共通の仕組みで扱う。

ExternalConnectionはOrganizationの子Entityである。

---

# Service

Serviceは、
StageArtが接続可能な外部サービスの種類を表す
Master Domainである。

例）

- X
- Instagram
- Facebook
- YouTube
- TikTok
- LINE
- Google
- Google Drive

SNSはServiceの一種として扱う。

SNS専用Domainは作成しない。

ServiceはExternalConnectionから参照される。

---

# Credential

Credentialは、
ExternalConnectionが外部サービスへ接続するために必要とする
認証情報を表す。

    ExternalConnection
           ├── Service
           └── Credential

Credentialには、

- OAuth
- Access Token
- Refresh Token
- API Key
- Secret

などの認証情報を扱える構造を持たせる。

Credentialは、
StageArt内部のAccountとは別の概念である。

認証情報は平文で保存しない。

---

# Project

Projectは、
Organization内部で行われる制作活動を管理する
Internal Domainである。

ProjectはPublic APIには公開しない。

Projectは、
ProductionなどのBusiness Resourceを
内部的に管理する。

利用者は通常、
Projectの存在を意識しない。

---

# Production

Productionは、
Organizationが行う公開公演を表す
Business Resourceである。

ProductionはProjectと内部的に関連する。

ただし、
ProjectはPublic Resourceとして公開しない。

Productionは、

- Performance
- Participant
- Public Information

などの公演情報を管理する。

---

# Performance

Performanceは、
Productionに属する個々の公演回を表す。

    Production
       ↓
    Performance

観客による予約は、
Performance単位で行われる。

Performanceは、

- 開演日時
- 終演日時
- 会場
- 公開状態
- 公演状態

などを管理する。

---

# Participant

Participantは、
Productionへの参加を表すBusiness Resourceである。

Participantは、
Subjectを通じて活動主体を参照する。

Subjectは、

- Person
- Organization

などの活動主体を表す共通Referenceである。

Participantは、
PersonやOrganizationへ直接依存しない。

---

# Reservation

Reservationは、
Performanceに対する来場予約を表すBusiness Resourceである。

    Performance
       ↓
    Reservation

ReservationはAggregate Rootである。

Reservationは、

- Booker
- HandledParticipant
- Companion
- ReservationSeat
- TicketType
- QRCode
- Status

などを管理する。

CompanionおよびReservationSeatは、
Reservationの内部Entityとして扱う。

ReservationはHistoryを直接管理しない。

---

# Person

Personは、
StageArtに登録された人物を表すBusiness Resourceである。

Personは、
認証情報を表すAccountとは独立したDomainである。

Personは、

- Profile
- Public Organization
- History

などの情報を扱う。

---

# History

Historyは、
Personの活動履歴を表す独立したDomainである。

Historyは、
Personから参照される。

Historyは読み取り専用として扱う。

History自身を直接編集するAPIは提供しない。

Historyは、
Domain Eventによって自動生成・更新される。

---

# Domain Relationship

主要なDomain Relationshipは以下とする。

    Organization
       │
       ├── Membership
       │       └── Person
       │
       ├── ExternalConnection
       │       ├── Service
       │       └── Credential
       │
       └── Project
              └── Production
                     ├── Performance
                     │      └── Reservation
                     │
                     └── Participant
                            └── Subject

Personは、

    Person
       └── History

という関係を持つ。

---

# Aggregate

StageArtでは、
DomainごとにAggregateを定義する。

主要Aggregate Rootは、

- Organization
- Production
- Performance
- Reservation
- Person

などである。

Aggregate内部のEntityは、
Aggregate Rootを経由して操作する。

---

# Child Entity

子Entityは、
親Aggregateまたは親Domainの責務として管理する。

例えば、

    Organization
    └── ExternalConnection

    ExternalConnection
    └── Credential

    Reservation
    ├── Companion
    └── ReservationSeat

などである。

子Entityを独立したPublic APIとして公開する必要がない場合、
親Resourceを経由して操作する。

---

# Reference

Domain間で情報を共有する場合、
必要に応じてReferenceを利用する。

例えば、

    Participant
        ↓
    Subject
        ↓
    Person

のように、
別DomainのEntityそのものを直接所有しない。

---

# Domain Event

Domain間のBusiness Processは、
Domain Eventによって連携する。

例えば、

    ProductionCreated
        ↓
    初期設定

    ReservationCreated
        ↓
    Business Process

    ParticipantAdded
        ↓
    History更新

などを行う。

Domain Eventには、
SecretやCredentialなどの
機密情報を含めない。

---

# External Service Boundary

外部サービスとの接続は、
ExternalConnectionを境界として扱う。

    Domain
       ↓
    ExternalConnection
       ↓
    Service
       ↓
    Infrastructure Adapter
       ↓
    External Service

Domain Layerは、
外部サービスのAPI仕様へ直接依存しない。

外部サービスごとの差異は、
Infrastructure LayerのAdapterで吸収する。

---

# Credential Security

Credentialは、
ExternalConnectionに属する認証情報として扱う。

以下の情報はSecretとして扱う。

- Access Token
- Refresh Token
- API Key
- Secret
- Client Secret
- Password

Secret情報は、

- API Response
- Domain Event
- Log
- Audit Log
- Error Message

へ出力してはならない。

具体的な暗号化およびSecret Storageは、
Infrastructure Layerが担当する。

---

# Authorization Boundary

OrganizationはTenant境界である。

Organizationに属するBusiness Dataは、
Organization Scopeによってアクセス制御する。

また、
Productionなどの個別Resourceについては、
PrimaryManagerおよびDelegateRoleなどの
Resource Scope権限を利用できる。

ExternalConnectionについては、
Organization管理権限を基本とする。

---

# History Boundary

Historyは、
Business Eventから自動的に生成される。

Domain Modelから直接Historyを編集しない。

以下のようなEventを契機として
Historyを更新できる。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved
- ReservationCreated
- ReservationCheckedIn
- ReservationCancelled

ExternalConnectionやCredentialなどの
技術的な接続情報は、
Personの活動Historyを生成しない。

---

# Publication Boundary

外部サービスへの投稿は、
ExternalConnectionとは別のBusiness Domainとして扱う。

ExternalConnectionは、

「外部サービスへ接続できる状態」

を提供する。

Publicationは、

- 投稿内容
- 投稿対象
- 投稿日時
- 投稿結果

などを管理する。

将来的に、

    Publication
        ↓
    ExternalConnection
        ↓
    Service
        ↓
    External Service

という構造で一括投稿を実現する。

---

# Design Principles

- Domain First
- User First
- Simple UI, Rich Domain
- Multi Tenant
- API First
- Mobile Ready
- Event Driven
- Single Source of Truth
- Fact and Artifact
- Backward Compatibility
- Plugin First
- Framework Independent
- Incremental Development
- Blueprint First
- Theatre First
- UI Theme and Design System

Domain Modelは、
これらのDesign Principlesに従って設計する。

---

# Domain Independence

Domain Modelは、

- WordPress
- REST Framework
- Database
- CSS
- JavaScript
- 外部API
- UI Framework

などのInfrastructureへ直接依存しない。

Infrastructureは、
Domain Modelを実現するための手段として扱う。

---

# Future

将来的に以下のDomainを追加できる構造とする。

- Publication
- Accounting
- Budget
- Document
- Notification
- Venue
- Ticket
- Goods
- Sponsor
- Fan Club
- Streaming
- External Service Analytics

新しいDomainを追加する場合も、
既存Domainの責務を不必要に拡張しない。

---

# Design Decisions

StageArtでは、
Domain ModelをBusiness Logicの中心とする。

OrganizationをTenant境界とする。

ProjectはInternal Domainとして扱う。

ProductionはPublic Business Resourceとして扱う。

PerformanceはProduction配下の公演回として扱う。

ReservationはPerformance配下の予約Aggregateとして扱う。

ParticipantはSubjectを通じて活動主体を参照する。

PersonはAccountとは独立したDomainとして扱う。

Historyは独立Domainとして管理し、
Domain Eventによって自動更新する。

ExternalConnectionはOrganizationの子Entityとして管理する。

ExternalConnectionはSNSに限定しない。

Serviceは外部サービスの種類を表すMaster Domainとする。

CredentialはExternalConnectionに属する認証情報として管理する。

StageArt内部のAccountとExternal Credentialを分離する。

外部サービス固有の処理はInfrastructure Layerへ隔離する。

Bulk PublicationはExternalConnectionとは別のDomainとして管理する。

Business RuleはDomain Layerが管理する。

Blueprintを唯一の設計基準とする。
