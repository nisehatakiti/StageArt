# StageArt Blueprint
# Logical ER Diagram

Version : 4.0

---

# Purpose

Logical ER Diagramは、StageArtの各Domainおよび関連Entityの
論理構造と関係を表現する。

Conceptual ERで定義したBusiness上の関係を、
EntityおよびReferenceとして具体化する。

Database製品固有の型やインデックスなどの物理設計は、
Physical ERまたは実装設計で定義する。

---

# Logical Model

Production
    │
    ├── PrimaryManager ────── Person
    │
    ├── ProductionDelegate
    │       ├── Person
    │       └── DelegateRole
    │
    ├── Performance
    │       ├── Seat
    │       └── Reservation
    │               ├── ReservationSeat
    │               └── Companion
    │
    └── Participant
            └── Subject

Subject
    └── History

Organization
    ├── Membership
    ├── ExternalConnection
    │       ├── Service
    │       └── Credential
    │
    └── Project
            └── Production

---

# Entity Definitions

## Account

Accountは認証情報を表す。

主な識別子

AccountId

AccountはPersonとは独立した概念として管理する。

---

## Person

PersonはStageArtにおける人物を表す。

主な識別子

PersonId

Accountとの紐付けは任意とする。

---

## Organization

Organizationは劇団、制作会社、企業などの団体を表す。

主な識別子

OrganizationId

OrganizationはStageArtにおけるTenantである。

OrganizationはMembershipおよびExternalConnectionを管理する。

OrganizationはProjectを管理する。

---

## Membership

MembershipはPersonとOrganizationの所属関係を表す。

主な識別子

MembershipId

主なReference

PersonId
OrganizationId

MembershipはOrganization単位の所属・権限を表す。

Production単位の管理権限とは別に管理する。

---

## ExternalConnection

ExternalConnectionは、
Organizationと外部サービスとの接続関係を表す。

主な識別子

ExternalConnectionId

主なReference

OrganizationId
ServiceId
CredentialId

主な情報

ExternalConnectionId
OrganizationId
ServiceId
CredentialId
AccountIdentifier
Status
CreatedAt
CreatedBy
UpdatedAt
UpdatedBy

ExternalConnectionはOrganizationの子Entityである。

一つのOrganizationは、
0個以上のExternalConnectionを持つことができる。

ExternalConnectionはSNS専用ではない。

SNS、動画サービス、クラウドサービス、
メッセージングサービスなど、
StageArtが外部連携するサービスを共通して扱う。

---

## ExternalConnection - Account Identifier

AccountIdentifierは、
外部サービス上の接続先Accountを識別する情報である。

例えば、

- User Name
- Account ID
- Page ID
- Channel ID

などを表現する。

AccountIdentifierは、
StageArt内部のAccountとは異なる。

StageArt内部のAccountはStageArtへの認証を表す。

AccountIdentifierは外部サービス上のAccountを表す。

---

## ExternalConnection - Service

ExternalConnectionは一つのServiceを参照する。

ExternalConnection.ServiceId
    ↓
Service.ServiceId

Serviceは外部サービスの種類を表すMasterである。

SNSはServiceの一種として扱う。

ExternalConnection自身に、
SNS固有の属性を持たせない。

---

## ExternalConnection - Credential

ExternalConnectionは一つのCredentialを保持する。

ExternalConnection.CredentialId
    ↓
Credential.CredentialId

CredentialはExternalConnectionに属する。

Credentialは外部サービスへの認証情報を表す。

Credentialは単独のPublic Resourceとして公開しない。

---

## Service

Serviceは、
StageArtが接続可能な外部サービスの種類を表すMaster Domainである。

主な識別子

ServiceId

主な情報

ServiceId
Code
Name
Description
ServiceType
AuthenticationType
Status

Serviceは複数のExternalConnectionから参照できる。

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

---

## ServiceType

ServiceTypeは、
外部サービスの分類を表す。

例）

- SOCIAL
- VIDEO
- CLOUD_STORAGE
- MESSAGING
- OTHER

ServiceTypeは分類および検索に利用する。

ServiceTypeによって
ExternalConnectionの構造を変更してはならない。

---

## Service AuthenticationType

AuthenticationTypeは、
Serviceが要求する認証方式を表す。

例）

- OAUTH
- API_KEY
- SECRET
- NONE

AuthenticationTypeは、
Credentialの利用方法を決定するために利用する。

実際の認証処理はInfrastructure Layerが担当する。

---

## Service Capabilities

Serviceは、
StageArtから利用可能な機能を定義できる。

例えば、

- TEXT_POST
- IMAGE_POST
- VIDEO_POST
- LINK_POST
- VIDEO_UPLOAD
- FILE_UPLOAD

などを想定する。

CapabilitiesはServiceの機能差を表す。

実際の外部API操作はInfrastructure Adapterが担当する。

---

## Credential

Credentialは、
ExternalConnectionが外部サービスへ接続するために必要とする
認証情報を表す。

主な識別子

CredentialId

主なReference

ExternalConnectionId

主な情報

CredentialId
ExternalConnectionId
CredentialStatus
CreatedAt
CreatedBy
UpdatedAt
UpdatedBy

CredentialのSecret値そのものは、
通常のLogical Entity属性として平文管理しない。

Secret値の保存先および暗号化方式は
Infrastructure Layerで定義する。

---

## Credential Authentication Data

Credentialでは、
ServiceのAuthenticationTypeに応じて
以下の認証情報を扱える。

OAuth

- AccessToken
- RefreshToken
- TokenExpiresAt
- Scope

API Key

- APIKey

Secret

- Secret
- ClientSecret
- Password

これらのSecret情報は、
Domain Event、API Response、Log、Audit Logへ
出力してはならない。

---

## Credential Status

Credentialは以下の状態を持つ。

- ACTIVE
- EXPIRED
- INVALID
- REVOKED

ACTIVE

認証情報が有効な状態。

EXPIRED

認証情報の有効期限が切れている状態。

INVALID

外部サービスによって認証情報が無効と判断された状態。

REVOKED

認証情報が明示的に無効化された状態。

---

## ExternalConnection Status

ExternalConnectionは以下の接続状態を持つ。

- CONNECTED
- DISCONNECTED
- ERROR

CONNECTED

外部サービスとの接続が有効な状態。

DISCONNECTED

外部サービスとの接続を切断した状態。

ERROR

認証失敗や外部サービス障害などにより、
接続に問題が発生している状態。

Credential StatusとExternalConnection Statusは
別の状態として管理する。

---

## Project

ProjectはOrganizationが管理する制作プロジェクトを表す。

主な識別子

ProjectId
OrganizationId

ProjectはInternal Domainであり、
公開APIには直接公開しない。

---

## Production

Productionは公開される公演を表す。

主な識別子

ProductionId
OrganizationId
ProjectId

主なReference

PrimaryManagerId

ProductionはProjectによって内部的に管理される。

Productionは一人のPrimaryManagerを持つ。

PrimaryManagerはPersonを参照する。

---

## Production Primary Manager

PrimaryManagerは、
Productionの管理責任者を表す。

Productionは必ず一人のPrimaryManagerを参照する。

主なReference

Production.PrimaryManagerId
    ↓
Person.PersonId

PrimaryManagerはProductionに関する
すべての管理権限を持つ。

PrimaryManagerにはDelegateRoleを設定しない。

PrimaryManagerはPersonのRoleではなく、
Productionに対する管理権限として扱う。

---

## ProductionDelegate

ProductionDelegateは、
PrimaryManagerからProductionの管理権限を委任されたPersonを表す。

Productionに属する子Entityとして管理する。

主な識別子

ProductionDelegateId

主なReference

ProductionId
PersonId
DelegateRoleId

ProductionDelegateは以下の情報を持つ。

ProductionDelegateId
ProductionId
PersonId
DelegateRoleId
CreatedAt
CreatedBy
UpdatedAt
UpdatedBy

一つのProductionには、
0人以上のProductionDelegateを設定できる。

---

## ProductionDelegate - Person

ProductionDelegateは一人のPersonを参照する。

ProductionDelegate.PersonId
    ↓
Person.PersonId

同一Personを複数のProductionに
ProductionDelegateとして登録できる。

また、同一Personであっても、
Productionごとに異なるDelegateRoleを設定できる。

例えば、

Production A
    ↓
Person A
    ↓
REHEARSAL_MANAGER

Production B
    ↓
Person A
    ↓
RESERVATION_MANAGER

という設定を許可する。

---

## DelegateRole

DelegateRoleは、
ProductionDelegateへ付与する権限セットを表すマスターである。

主な識別子

DelegateRoleId

主な情報

DelegateRoleId
Code
Name
Description
Status

DelegateRoleはPersonに直接紐付かない。

ProductionDelegateを介して、
特定ProductionにおけるPersonの権限を定義する。

---

## DelegateRole Permission

DelegateRoleは、
あらかじめ定義された権限セットを持つ。

論理的には、

DelegateRole
    ↓
Permission Set

という関係を持つ。

Permissionの具体的な物理構造は、
Authorization設計で定義する。

Logical ERでは、
DelegateRoleが権限セットを参照する概念のみを定義する。

---

## Performance

PerformanceはProductionに属する公演回を表す。

主な識別子

PerformanceId
ProductionId

PerformanceはProductionに所属する。

---

## Participant

ParticipantはProductionへの参加を表す。

主な識別子

ParticipantId
ProductionId
SubjectId

ParticipantはPersonまたはOrganizationを直接参照しない。

SubjectIdを介して活動主体を参照する。

Participantは以下の情報を持つ。

ParticipantId
ProductionId
SubjectId
ParticipantType
Role
CreditOrder
Visibility
Status

---

## Subject

SubjectはBusiness上の活動主体を
共通Referenceとして表現する。

主な識別情報

SubjectType
SubjectId

SubjectType

PERSON
ORGANIZATION

SubjectはPersonまたはOrganizationを表す。

Subjectは独立したBusiness Entityではなく、
PersonおよびOrganizationを共通の参照形式で扱うための概念である。

---

## Category

CategoryはProductionの分類を表す。

主な識別子

CategoryId

ProductionからCategoryを参照する。

---

## Genre

GenreはProductionのジャンルを表す。

主な識別子

GenreId

ProductionとGenreは多対多の関係を持つ。

---

## Tag

TagはProductionに付与するタグを表す。

主な識別子

TagId

ProductionとTagは多対多の関係を持つ。

---

## Seat

SeatはPerformanceに属する座席を表す。

主な識別子

SeatId
PerformanceId

Seatは座席情報のみを保持する。

Seat自身は予約状態を保持しない。

SeatはCheck Inの対象ではない。

予約状態はReservationとReservationSeatの関係から判断する。

---

## Reservation

ReservationはPerformanceに対する予約を表す。

主な識別子

ReservationId
PerformanceId
BookerId
HandledParticipantId

Reservationは以下を管理する。

ReservationId
PerformanceId
BookerId
HandledParticipantId
TicketType
QRCode
Status
CreatedBy
CreatedAt
UpdatedBy
UpdatedAt

BookerIdは予約者であるPersonを参照する。

HandledParticipantIdは、
予約における「○○扱い」のParticipantを参照する。

HandledParticipantIdは任意である。

CreatedByはReservationを作成した主体を表す。

UpdatedByはReservationを最後に変更した主体を表す。

---

## ReservationSeat

ReservationSeatはReservationに紐付く予約座席を表す。

主な識別子

ReservationSeatId
ReservationId
SeatId

ReservationSeatはReservationの子Entityである。

ReservationSeatはReservationを経由してのみ変更できる。

ReservationSeatはSeatを参照する。

ReservationSeat自体はCheck In状態を保持しない。

---

## Companion

CompanionはReservationに属する同行者を表す。

主な識別子

CompanionId
ReservationId

CompanionはReservationの子Entityである。

Companion単独では存在しない。

---

## History

HistoryはSubjectの活動履歴を表す。

主な識別子

HistoryId
SubjectId
ProductionId
PerformanceId

Historyは以下の情報を持つ。

HistoryId
SubjectId
HistoryType
ParticipantType
ProductionId
PerformanceId
EventDateTime

PerformanceIdは任意である。

ParticipantTypeはHistoryTypeがPARTICIPATIONの場合のみ保持する。

---

# Reference Rules

## Organization → ExternalConnection

Organizationは0個以上のExternalConnectionを持つ。

ExternalConnection.OrganizationId
    ↓
Organization.OrganizationId

ExternalConnectionはOrganizationの子Entityとして管理する。

ExternalConnectionを異なるOrganization間で共有しない。

---

## ExternalConnection → Service

ExternalConnectionは必ず一つのServiceを参照する。

ExternalConnection.ServiceId
    ↓
Service.ServiceId

一つのServiceは複数のExternalConnectionから参照できる。

ServiceはMasterとして管理する。

---

## ExternalConnection → Credential

ExternalConnectionは一つのCredentialを保持する。

ExternalConnection.CredentialId
    ↓
Credential.CredentialId

CredentialはExternalConnectionの子Entityとして管理する。

Credentialは単独のPublic Resourceとして公開しない。

---

## Credential → ExternalConnection

Credentialは必ず一つのExternalConnectionに所属する。

Credential.ExternalConnectionId
    ↓
ExternalConnection.ExternalConnectionId

CredentialをOrganization間で共有しない。

---

## Production → PrimaryManager

Productionは一人のPrimaryManagerを参照する。

Production.PrimaryManagerId
    ↓
Person.PersonId

PrimaryManagerはPersonを直接参照する。

PrimaryManagerはProductionに対して全権限を持つ。

---

## Production → ProductionDelegate

Productionは0人以上のProductionDelegateを持つ。

ProductionDelegate.ProductionId
    ↓
Production.ProductionId

ProductionDelegateはProductionの子Entityとして管理する。

---

## ProductionDelegate → Person

ProductionDelegateは必ず一人のPersonを参照する。

ProductionDelegate.PersonId
    ↓
Person.PersonId

---

## ProductionDelegate → DelegateRole

ProductionDelegateは一つのDelegateRoleを参照する。

ProductionDelegate.DelegateRoleId
    ↓
DelegateRole.DelegateRoleId

DelegateRoleは、
ProductionDelegateに付与される権限セットを表す。

---

## ProductionDelegate Scope

ProductionDelegateの権限は、
Production単位で有効となる。

同一Personが複数ProductionのDelegateになる場合、
Productionごとに別のDelegateRoleを持つことができる。

Production A
    ↓
ProductionDelegate
    ├── PersonId = person-001
    └── DelegateRoleId = rehearsal-manager

Production B
    ↓
ProductionDelegate
    ├── PersonId = person-001
    └── DelegateRoleId = reservation-manager

---

## Participant → Subject

Participantは必ず一つのSubjectを参照する。

Participant.SubjectId
    ↓
Subject.SubjectId

ParticipantからPersonまたはOrganizationを直接参照しない。

---

## Reservation → Booker

Reservationは必ず一つのBookerを持つ。

Reservation.BookerId
    ↓
Person.PersonId

Bookerは予約者を表す。

---

## Reservation → HandledParticipant

Reservationは任意でHandledParticipantを持つ。

Reservation.HandledParticipantId
    ↓
Participant.ParticipantId

HandledParticipantが存在しないReservationも許可する。

HandledParticipantはReservationとParticipantの関係を表す。

---

## Reservation → ReservationSeat

Reservationは0人以上のReservationSeatを持つ。

ReservationSeat.ReservationId
    ↓
Reservation.ReservationId

Reservation人数の変更によって、
ReservationSeatを追加・解放できる。

Reservationのキャンセル時には、
関連するReservationSeatをすべて解放する。

---

## ReservationSeat → Seat

ReservationSeatは一つのSeatを参照する。

ReservationSeat.SeatId
    ↓
Seat.SeatId

SeatはPerformanceに属する。

ReservationSeatが参照するSeatは、
Reservationが所属するPerformanceのSeatでなければならない。

---

## History → Subject

Historyは必ず一つのSubjectを参照する。

History.SubjectId
    ↓
Subject.SubjectId

HistoryからPersonまたはOrganizationを直接参照しない。

---

## History → Production

Historyは必ず一つのProductionを参照する。

History.ProductionId
    ↓
Production.ProductionId

---

## History → Performance

Historyは必要に応じてPerformanceを参照する。

History.PerformanceId
    ↓
Performance.PerformanceId

PerformanceIdはNULLを許可する。

---

# Authorization Rules

## Organization Membership

MembershipはOrganization単位の権限を表す。

Person
    ↓
Membership
    ↓
Organization

---

## Organization ExternalConnection

ExternalConnectionはOrganization単位の外部サービス接続を表す。

Organization
    ↓
ExternalConnection
    ├── Service
    └── Credential

ExternalConnectionの管理権限は、
Organization Scopeの権限によって制御する。

CredentialのSecret値は、
管理権限を持つ利用者であっても通常のAPI Responseとして取得できない。

---

## Production Primary Manager

PrimaryManagerはProduction単位の管理責任者である。

Production
    ↓
PrimaryManager
    ↓
Person

PrimaryManagerはProductionに関する全権限を持つ。

---

## Production Delegate

ProductionDelegateはProduction単位の委任権限を持つ。

Production
    ↓
ProductionDelegate
    ├── Person
    └── DelegateRole

ProductionDelegateは、
DelegateRoleに定義された権限のみを持つ。

Organization Membershipの権限とは分離する。

---

# Aggregate Structure

Organization
├── Membership
├── ExternalConnection
│   └── Credential
└── Project
    └── Production
        ├── ProductionDelegate
        ├── Participant
        └── Performance
            ├── Seat
            └── Reservation
                ├── ReservationSeat
                └── Companion

ServiceはMaster Domainとして管理され、
ExternalConnectionから参照される。

PrimaryManagerはProductionからPersonを参照する。

ProductionDelegateはProductionに属する子Entityである。

Historyは上記Aggregateの子Entityではない。

Subject
└── History

Historyは独立したDomainとして管理する。

---

# Aggregate Root

Aggregate | Root
--- | ---
Organization | Organization
Project | Project
Production | Production
Performance | Performance
Reservation | Reservation

ServiceはMaster Domainであり、
Organization Aggregateの子Entityではない。

CredentialはExternalConnectionの子Entityである。

---

# Aggregate Rules

OrganizationはExternalConnectionを管理する。

ExternalConnectionの追加・変更・削除は、
Organizationの管理権限を通して行う。

ExternalConnectionはServiceを参照する。

ExternalConnectionはCredentialを管理する。

CredentialのSecret情報は、
ExternalConnectionを経由して管理する。

ServiceはOrganizationごとに複製しない。

同一Serviceを複数OrganizationのExternalConnectionから参照できる。

ProductionはProductionDelegateを管理する。

ProductionDelegateの追加・変更・削除は、
Productionの管理権限を通して行う。

ProductionはPrimaryManagerを一人保持する。

PrimaryManagerはProductionに対する全権限を持つ。

ProductionDelegateはDelegateRoleによって
権限を制限する。

Reservationは以下の子Entityを管理する。

- ReservationSeat
- Companion

これらはReservationを経由してのみ変更できる。

ParticipantはProductionに属する独立したDomain Entityである。

Historyは独立したDomainであり、
ParticipantやReservationのAggregateには含めない。

---

# Domain Event Relationship

HistoryはDomain Eventを契機として生成・更新される。

ParticipantAdded
        ↓
Participation History

ParticipantUpdated
        ↓
必要に応じてHistory更新

ParticipantRemoved
        ↓
過去のHistoryは削除しない

ReservationCheckedIn
        ↓
Audience History

以下のイベントではAudience Historyを生成しない。

ReservationCreated
ReservationUpdated
ReservationCancelled

ExternalConnectionCreated
        ↓
External Connection State

ExternalConnectionConnected
        ↓
Connection State

ExternalConnectionDisconnected
        ↓
Connection State

ExternalConnectionError
        ↓
Connection Error State

CredentialのSecret値は、
Domain Eventに含めない。

---

# Design Principles

- Logical ERはDomain Modelをデータ構造として表現する。
- Database製品固有の物理設計はLogical ERでは定義しない。
- Organization MembershipとProduction単位の権限を分離する。
- OrganizationはExternalConnectionを管理する。
- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionはServiceを参照する。
- ExternalConnectionはCredentialを保持する。
- ExternalConnectionはSNSに限定しない。
- Serviceは外部サービスの種類を表すMasterである。
- Serviceは複数のExternalConnectionから参照できる。
- CredentialはExternalConnectionの子Entityである。
- Credentialは単独のPublic Resourceとして公開しない。
- CredentialのSecret情報は平文で保存しない。
- CredentialのSecret情報をAPI Responseへ返却しない。
- CredentialのSecret情報をDomain Eventへ含めない。
- CredentialのSecret情報をLogへ出力しない。
- CredentialのSecret情報をAudit Logへ出力しない。
- ServiceのAuthenticationTypeはCredentialの認証方式を決定する。
- ServiceのCapabilitiesは外部サービスごとの機能差を表現する。
- ExternalConnectionの接続状態とCredentialの状態を分離する。
- Productionは一人のPrimaryManagerを参照する。
- PrimaryManagerはPersonを参照する。
- PrimaryManagerはProductionに関する全権限を持つ。
- Productionは0人以上のProductionDelegateを持つ。
- ProductionDelegateはPersonを参照する。
- ProductionDelegateはDelegateRoleを参照する。
- DelegateRoleはあらかじめ定義された権限セットを表す。
- DelegateRoleはProduction単位で適用される。
- 同一PersonがProductionごとに異なるDelegateRoleを持つことを許可する。
- ParticipantはSubjectを介して活動主体を参照する。
- PersonとOrganizationはSubjectによって共通化して参照する。
- ReservationはHandledParticipantを任意で参照できる。
- HandledParticipantはParticipantを参照する。
- CompanionはReservationの子Entityである。
- ReservationSeatはReservationの子Entityである。
- ReservationSeatはSeatを参照する。
- SeatはPerformanceに属する。
- Seatは予約状態を保持しない。
- SeatはCheck Inの対象ではない。
- Check InはReservation単位で行う。
- Historyは独立したDomainである。
- HistoryはSubjectを介して活動主体を参照する。
- HistoryはProductionを必ず参照する。
- PerformanceはHistoryに対して任意である。
- PARTICIPATION HistoryはParticipantTypeを保持する。
- AUDIENCE HistoryはParticipantTypeを保持しない。
- AUDIENCE HistoryのSubjectはReservation.Bookerである。
- HistoryはDomain Eventを契機として生成・更新する。
- 外部サービス固有のAPI仕様はLogical ERでは定義しない。
- 外部サービスへのAPI呼び出しはInfrastructure Layerで実装する。
- ExternalConnectionは特定の外部サービスAPIへ直接依存しない。
