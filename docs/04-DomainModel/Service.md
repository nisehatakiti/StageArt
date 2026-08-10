# StageArt Blueprint
# Domain Model : Service

Version : 1.0

---

# Purpose

Serviceは、
StageArtが接続可能な外部サービスの種類を表すMaster Domainである。

ServiceはExternalConnectionから参照される。

ExternalConnectionは、
OrganizationとServiceの接続関係を表す。

---

# Concept

Serviceは、
外部サービスそのものの種類を表す。

例えば、

- X
- Instagram
- Facebook
- YouTube
- TikTok
- LINE
- Google
- Google Drive

などをServiceとして管理する。

SNSはServiceの一種であり、
SNS専用のDomainとして管理しない。

---

# Relationship

ServiceはExternalConnectionから参照される。

    Organization
    └── ExternalConnection
           ├── Service
           └── Credential

一つのServiceは、
複数のExternalConnectionから参照できる。

例えば、

    Service
    └── Instagram
          ↑
          ├── Organization A / ExternalConnection
          ├── Organization B / ExternalConnection
          └── Organization C / ExternalConnection

---

# Identity

ServiceはServiceIdによって一意に識別する。

ServiceIdは変更できない。

Service Codeをシステム内部の識別子として利用する。

例）

- X
- INSTAGRAM
- FACEBOOK
- YOUTUBE
- TIKTOK
- LINE
- GOOGLE
- GOOGLE_DRIVE

Service Nameは表示用名称であり、
変更できる。

---

# Service Information

Serviceは以下の情報を管理する。

- ServiceId
- Code
- Name
- Description
- ServiceType
- AuthenticationType
- Status

---

# ServiceType

ServiceTypeは、
外部サービスの分類を表す。

例）

- SOCIAL
- VIDEO
- CLOUD_STORAGE
- MESSAGING
- OTHER

ServiceTypeは、
Serviceの分類および検索に利用する。

ServiceTypeによって
ExternalConnectionの構造を変更してはならない。

---

# AuthenticationType

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

# Capabilities

Serviceは、
StageArtから利用可能な機能を定義できる。

例えばSNSの場合、

- TEXT_POST
- IMAGE_POST
- VIDEO_POST
- LINK_POST

などを想定する。

動画サービスの場合、

- VIDEO_UPLOAD
- VIDEO_UPDATE

などを想定する。

Capabilitiesは、
ExternalConnectionの接続状態とは別の概念である。

ServiceがCapabilityを持っていても、
そのOrganizationのExternalConnectionが
CONNECTEDでなければ利用できない。

---

# Service Capability

Service Capabilityは、
StageArtがそのServiceに対して
どのような操作を実行できるかを表す。

例えば、

    Service = X

    Capabilities
    ├── TEXT_POST
    ├── IMAGE_POST
    ├── VIDEO_POST
    └── LINK_POST

Instagramの場合、

    Service = INSTAGRAM

    Capabilities
    ├── IMAGE_POST
    ├── VIDEO_POST
    └── LINK_POST

などのように定義できる。

実際に利用できるCapabilityは、
Service Adapterの実装状況によって決定される。

---

# Status

Serviceは利用可能状態を持つ。

基本状態は以下とする。

- ACTIVE
- INACTIVE

---

## ACTIVE

StageArtから利用可能なService。

新しいExternalConnectionを作成できる。

---

## INACTIVE

StageArtから新規接続できないService。

既存のExternalConnectionについては、
既存接続を維持する場合がある。

既存Connectionへの影響は、
Service Lifecycleのルールに従う。

---

# Service Registration

ServiceはStageArtが管理するMaster Dataである。

Organizationが自由にServiceを追加することはできない。

Serviceの追加・変更・無効化は、
StageArt管理者によって行う。

Organizationは、
登録済みのServiceからExternalConnectionを作成する。

---

# ExternalConnection

ExternalConnectionはServiceを参照する。

ExternalConnectionは、
Service固有の情報を直接保持しない。

例えばInstagram接続であっても、

ExternalConnectionに

- InstagramUserName
- InstagramAccessToken

などのSNS固有項目を追加しない。

代わりに、

    ExternalConnection
    ├── Service
    ├── AccountIdentifier
    └── Credential

として管理する。

---

# Credential

Serviceは、
必要とするAuthenticationTypeに応じて
Credentialを利用する。

例えば、

    Service
    ├── AuthenticationType = OAUTH
    └── Credential

または、

    Service
    ├── AuthenticationType = API_KEY
    └── Credential

などの構成を取る。

Credentialの具体的な構造および
認証情報の安全な保存方法は、
Credential DomainおよびInfrastructure Layerで管理する。

---

# Infrastructure Adapter

Serviceごとの外部API仕様は、
Infrastructure LayerのAdapterによって吸収する。

例えば、

    Service
    ├── X
    │     └── XAdapter
    │
    ├── Instagram
    │     └── InstagramAdapter
    │
    ├── Facebook
    │     └── FacebookAdapter
    │
    └── YouTube
          └── YouTubeAdapter

などを想定する。

Domain Layerは、
各ServiceのAPI仕様へ直接依存しない。

---

# Service API

Serviceは、
外部サービスそのものを表すMaster Domainである。

Service自身に対して、
外部サービスAPIを実行することはない。

API呼び出しは、

    Application
        ↓
    ExternalConnection
        ↓
    Service
        ↓
    Infrastructure Adapter
        ↓
    External Service API

という構造で実行する。

---

# Bulk Publication

Serviceは、
将来的なBulk Publicationの投稿先として利用される。

例えば、

    Publication
        ↓
    ExternalConnection
        ↓
    Service
        ├── X
        ├── Instagram
        └── Facebook

という形で、
一つのPublicationを複数Serviceへ送信できる。

Serviceは投稿処理そのものを管理しない。

投稿内容、
投稿対象、
投稿日時、
投稿結果などは
Publication Domainが管理する。

---

# Service Availability

Serviceの利用可否は、
以下の複数条件によって決定される。

- Service Status
- ExternalConnection Status
- Authentication Status
- Infrastructure Adapter Availability
- Service API Availability

ServiceがACTIVEであっても、
外部API障害などによって一時的に利用できない場合がある。

この場合のRuntime状態は、
ExternalConnectionまたはInfrastructure Layerで管理する。

---

# Authorization

Service Master自体は、
Organization利用者が変更するものではない。

Organization利用者は、
Serviceを参照してExternalConnectionを作成・管理する。

ExternalConnectionの操作権限は、
ExternalConnectionおよびAuthorization Domainで管理する。

---

# Audit Information

Service Masterの変更については、
StageArt管理者による監査情報を保持する。

基本的な監査情報として、

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

を利用する。

---

# Domain Events

ServiceはMaster Domainであるため、
通常のOrganization操作によって
Domain Eventを発行するものではない。

Serviceの追加・変更・無効化など、
管理上重要な変更が発生した場合は、
必要に応じて管理用Eventを利用する。

---

# Business Rules

- Serviceは外部サービスの種類を表すMaster Domainである。
- ServiceはServiceIdによって識別する。
- Service Codeはシステム内部の識別子として利用する。
- Service Nameは表示用名称である。
- Serviceは複数のExternalConnectionから参照できる。
- OrganizationはServiceを直接所有しない。
- OrganizationはExternalConnectionを通じてServiceへ接続する。
- SNSはServiceの一種である。
- SNS専用のDomainを作成しない。
- ServiceTypeは外部サービスの分類を表す。
- AuthenticationTypeは外部サービスの認証方式を表す。
- CapabilitiesはStageArtから利用可能な操作を表す。
- Service StatusがINACTIVEの場合、新規ExternalConnectionを作成できない。
- Service固有のAPI処理はDomain Layerに実装しない。
- 外部API処理はInfrastructure Adapterが担当する。
- Serviceは特定のInfrastructure Adapterへ直接依存しない。
- OrganizationはServiceを追加・変更できない。
- Serviceの管理はStageArt管理者が行う。
- Service自身は外部APIを実行しない。
- Bulk PublicationはServiceとは別のDomainで管理する。

---

# Design Decisions

StageArtでは、
SNSを独立したDomainとして扱わない。

X、Instagram、Facebook、YouTubeなどは、
すべてServiceとして共通化する。

ExternalConnectionはServiceを参照する。

ServiceはMaster Dataとして管理する。

外部サービスごとの差異は、
AuthenticationType、Capabilities、
Infrastructure Adapterによって吸収する。

Service固有のBusiness Logicを
Domain Modelへ持ち込まない。

これにより、
新しい外部サービスを追加する場合でも、
ExternalConnectionやOrganizationの
Domain構造を変更せずに対応できる設計とする。

---

# Future

将来的に以下へ対応する。

- Service追加
- Service Capability管理
- OAuth Provider管理
- API Version管理
- Service Rate Limit管理
- Service Health Check
- Service Maintenance Status
- External Service Analytics
- Scheduled Publication
- Bulk Publication
- Service-specific Feature Flags

---

# Design Principles

- Serviceは外部サービスの種類を表すMaster Domainである。
- ServiceはExternalConnectionから参照される。
- SNSはServiceの一種として扱う。
- SNS専用Domainを作成しない。
- Service固有のAPI仕様はInfrastructure Layerへ隔離する。
- AuthenticationTypeによって認証方式を抽象化する。
- CapabilitiesによってServiceごとの機能差を表現する。
- Serviceの利用可否とExternalConnectionの接続状態を分離する。
- ServiceはOrganizationから直接管理しない。
- ServiceはMaster Dataとして管理する。
- Business RuleはDomain Layerが管理する。
- APIはApplication Layerの公開インターフェースとして機能する。
