# StageArt Blueprint
# API : Organization

Version : 2.0

---

# Purpose

Organization APIはOrganizationドメインを操作するためのREST APIを定義する。

Organization APIは劇団・プロデュース団体・演劇サークルなど、
舞台芸術活動を行う団体の管理を担当する。

Organizationは公開Resourceであり、
Projectなどの内部ドメインはAPIとして公開しない。

OrganizationはStageArtにおけるTenantであり、
Organizationに属するBusiness ResourceはOrganization単位で管理する。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

/api/v1/organizations

Organization固有の操作はOrganization Resourceとして公開する。

/api/v1/organizations/{organizationId}

---

# Public Resource

Organization APIが公開する情報

- Organization
- Member
- Production
- ExternalConnection
- Service

Credentialは公開Resourceとして提供しない。

Organizationに関連する内部ドメイン(Projectなど)は公開しない。

利用者はProjectの存在を意識しない。

---

# Create Organization

## Request

POST /api/v1/organizations

### Request Body

{
  "name": "劇団StageArt",
  "description": "千葉県で活動する劇団です。"
}

### Success

201 Created

### Business Rules

- Organizationを新規作成する。
- OrganizationCreatedを発行する。
- 作成者をOwnerとしてMembershipへ登録する。
- 初期設定はDomain Eventによって実行する。

---

# Get Organization

## Request

GET /api/v1/organizations/{organizationId}

### Response

取得可能情報

- Organization
- Public Profile
- Members
- Productions
- ExternalConnection Summary

ExternalConnectionについては、
以下の安全な情報のみ返却する。

- ExternalConnectionId
- Service
- AccountIdentifier
- Status

CredentialのSecret情報は返却しない。

---

# Update Organization

## Request

PUT /api/v1/organizations/{organizationId}

更新可能項目

- Name
- Description
- Logo
- Website
- SNS

OrganizationIdは変更できない。

SNSは外部サービス接続情報そのものを直接保持する項目ではない。

外部サービスとの接続はExternalConnectionで管理する。

---

# Archive Organization

## Request

PATCH /api/v1/organizations/{organizationId}/archive

### Business Rules

- OrganizationStatusをArchivedへ変更する。
- OrganizationArchivedを発行する。
- 過去データは保持する。

---

# Delete Organization

Version 2.0では物理削除しない。

Organizationは論理削除を採用する。

---

# List Organizations

## Request

GET /api/v1/organizations

### Query Parameters

page

pageSize

keyword

status

sort

---

# Search

検索対象

- Organization Name
- Description
- Activity Region

ExternalConnectionのCredentialやSecret情報は検索対象としない。

---

# Child Resources

Organization配下の公開Resource

GET    /organizations/{id}/members

GET    /organizations/{id}/productions

GET    /organizations/{id}/external-connections

Version 2.0ではExternalConnectionを公開する。

ProjectはInternal Domainであるため、
公開APIとして提供しない。

---

# ExternalConnection

Organizationは複数のExternalConnectionを持つことができる。

Organization
└── ExternalConnection
       ├── Service
       └── Credential

ExternalConnectionは、
Organizationと外部サービスとの接続関係を表す。

ExternalConnectionはSNS専用ではない。

SNS、動画サービス、クラウドサービス、
メッセージングサービスなど、
StageArtが外部連携するサービスを共通して扱う。

---

# List ExternalConnections

## Request

GET /api/v1/organizations/{organizationId}/external-connections

### Query Parameters

service

status

keyword

sort

### Response

取得可能情報

- ExternalConnectionId
- Service
- AccountIdentifier
- Status
- CredentialStatus

CredentialのSecret情報は返却しない。

---

# Create ExternalConnection

## Request

POST /api/v1/organizations/{organizationId}/external-connections

### Request Body

{
  "serviceId": "...",
  "accountIdentifier": "example-account"
}

認証方式に応じて、
OAuthなどの認証フローを開始する。

### Success

201 Created

### Business Rules

- ExternalConnectionを作成する。
- Organizationへ紐付ける。
- Serviceを参照する。
- Credentialを生成または登録する。
- ExternalConnectionCreatedを発行する。
- 認証に成功した場合はCONNECTED状態とする。

---

# Get ExternalConnection

## Request

GET /api/v1/external-connections/{externalConnectionId}

### Response

取得可能情報

- ExternalConnectionId
- Service
- AccountIdentifier
- Status
- CredentialStatus
- CreatedAt
- UpdatedAt

Secret情報は返却しない。

---

# Update ExternalConnection

## Request

PUT /api/v1/external-connections/{externalConnectionId}

更新可能項目

- AccountIdentifier
- Credential

Service変更については、
既存Credentialとの整合性を確認する。

必要に応じて再認証を要求する。

ExternalConnectionIdは変更できない。

Statusは専用操作によって変更する。

---

# Connect ExternalConnection

## Request

PATCH /api/v1/external-connections/{externalConnectionId}/connect

### Business Rules

- 外部サービスとの接続処理を開始する。
- 認証処理をInfrastructure Layerへ委譲する。
- 認証に成功した場合、StatusをCONNECTEDへ変更する。
- ExternalConnectionConnectedを発行する。

---

# Disconnect ExternalConnection

## Request

PATCH /api/v1/external-connections/{externalConnectionId}/disconnect

### Business Rules

- 外部サービスとの接続を切断する。
- ExternalConnectionのStatusをDISCONNECTEDへ変更する。
- ExternalConnectionDisconnectedを発行する。
- ExternalConnection自体は削除しない。

Credentialは必要に応じて保持する。

---

# Delete ExternalConnection

## Request

DELETE /api/v1/external-connections/{externalConnectionId}

### Business Rules

- ExternalConnectionをOrganizationから削除する。
- 関連するCredentialを安全に破棄する。
- 外部サービスとの接続を無効化する。
- ExternalConnectionDeletedを発行する。

Secret情報の物理的な削除はInfrastructure Layerが担当する。

---

# Service

Serviceは、
StageArtが接続可能な外部サービスの種類を表すMaster Domainである。

例

- X
- Instagram
- Facebook
- YouTube
- TikTok
- LINE
- Google
- Google Drive

SNSはServiceの一種として扱う。

OrganizationはServiceを直接所有しない。

OrganizationはExternalConnectionを通じてServiceを利用する。

---

# Service Reference

ExternalConnectionは一つのServiceを参照する。

ExternalConnection
       ↓
     Service

Serviceは複数のExternalConnectionから参照できる。

Service固有のAPI処理はOrganization APIでは実行しない。

外部サービスごとの差異はInfrastructure LayerのAdapterで吸収する。

---

# List Services

## Request

GET /api/v1/services

### Query Parameters

type

status

keyword

### Response

取得可能情報

- ServiceId
- Code
- Name
- Description
- ServiceType
- AuthenticationType
- Status
- Capabilities

Credential情報は返却しない。

---

# Credential

CredentialはExternalConnectionに属する認証情報である。

ExternalConnection
└── Credential

Credentialは単独のPublic Resourceとして公開しない。

Credentialに含まれる、

- Access Token
- Refresh Token
- API Key
- Secret
- Client Secret
- Password

などはAPI Responseへ返却しない。

---

# Credential Management

Credentialの作成・更新・削除は、
ExternalConnectionを経由して行う。

Credential単独の公開APIは提供しない。

OAuthの場合は、
OAuth認証フローによってCredentialを生成・更新する。

認証情報の保存・暗号化・Secret Storageは
Infrastructure Layerが担当する。

---

# ExternalConnection Status

ExternalConnectionは以下の状態を持つ。

- CONNECTED
- DISCONNECTED
- ERROR

CONNECTED

外部サービスとの接続が有効な状態。

DISCONNECTED

外部サービスとの接続を切断した状態。

ERROR

認証失敗などにより接続に問題が発生している状態。

---

# Authorization

認可はMembershipによって行う。

Roleに応じて実行可能なAPIを制御する。

例

- Owner
- Administrator
- Manager
- Member

---

# ExternalConnection Authorization

ExternalConnectionはOrganization ScopeのResourceとして扱う。

基本的にはOrganizationの管理権限を持つ利用者が
ExternalConnectionを管理できる。

必要に応じてDelegateRoleへ、
ExternalConnectionに関する権限を追加できる。

例えば、

- ExternalConnection.Read
- ExternalConnection.Create
- ExternalConnection.Update
- ExternalConnection.Delete
- ExternalConnection.Connect
- ExternalConnection.Disconnect

などを定義できる。

具体的なPermission設計はAuthorization Domainで管理する。

---

# Production Management

Organization全体の権限と、
Production単位の権限は分離する。

Productionには、

Production
├── PrimaryManager
└── ProductionDelegate
       ├── Person
       └── DelegateRole

を設定できる。

PrimaryManagerはProductionに対する全権限を持つ。

ProductionDelegateはDelegateRoleによって
あらかじめ定義された権限のみを持つ。

Production単位の管理権限は、
Production APIおよびProduction Domainで管理する。

---

# Domain Events

Organization APIは以下のDomain Eventを利用する。

- OrganizationCreated
- OrganizationArchived

将来的に

- OrganizationDeleted

を追加する。

ExternalConnectionについては以下を利用する。

- ExternalConnectionCreated
- ExternalConnectionUpdated
- ExternalConnectionConnected
- ExternalConnectionDisconnected
- ExternalConnectionError
- ExternalConnectionDeleted

CredentialのSecret情報は、
Domain Eventへ含めない。

---

# Error Response

代表例

400 Bad Request

401 Unauthorized

403 Forbidden

404 Not Found

409 Conflict

422 Validation Error

500 Internal Server Error

---

# Response Security

API Responseには、
外部サービスの認証情報を含めない。

以下は返却禁止とする。

- Access Token
- Refresh Token
- API Key
- Secret
- Client Secret
- Password

ExternalConnectionのResponseでは、
Serviceおよび接続状態などの安全な情報のみを返却する。

---

# External Service Boundary

Organization APIは、
外部サービスAPIを直接呼び出さない。

Organization API
        ↓
Application Layer
        ↓
ExternalConnection
        ↓
Service
        ↓
Infrastructure Adapter
        ↓
External Service API

外部サービスごとのAPI仕様差異は、
Infrastructure Layerで吸収する。

---

# Error Response

代表例

400 Bad Request

401 Unauthorized

403 Forbidden

404 Not Found

409 Conflict

422 Validation Error

500 Internal Server Error

---

# Future

将来的に以下を追加する。

- Organization Invite
- Organization Statistics
- Organization Homepage
- Organization Settings
- ExternalConnection Health Check
- OAuth Reauthorization
- Bulk Publication
- Scheduled Publication
- External Service Analytics

これらはOrganization Resourceまたは
関連Business Resourceの配下として公開する。

ProjectなどのInternal Domainは、
引き続き公開APIとして提供しない。

---

# Design Principles

- Organizationは公開Resourceである。
- OrganizationはStageArtにおけるTenantである。
- ProjectはInternal Domainであり公開しない。
- APIはRESTを採用する。
- Business RuleはDomain Layerが管理する。
- Domain Eventを利用してBusiness Processを開始する。
- Organizationは論理削除を採用する。
- OrganizationはMembershipによって認可する。
- OrganizationとProductionの管理権限を分離する。
- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionはSNSに限定しない。
- SNSはServiceの一種として扱う。
- ServiceはMaster Domainとして管理する。
- CredentialはExternalConnectionに属する。
- Credentialは単独のPublic Resourceとして公開しない。
- CredentialのSecret情報はAPI Responseへ返却しない。
- CredentialのSecret情報をLogへ出力しない。
- CredentialのSecret情報をDomain Eventへ含めない。
- CredentialのSecret情報をAudit Logへ出力しない。
- 外部サービスAPIへのアクセスはInfrastructure Layerが担当する。
- 外部サービス固有の処理はInfrastructure Adapterへ隔離する。
- PrimaryManagerはProductionに対する全権限を持つ。
- ProductionDelegateはDelegateRoleによって権限を制限する。
- Organization MembershipとProduction単位の権限を分離する。
- APIはApplication Layerの公開インターフェースとして機能する。
- APIはBusiness Resourceを公開する。
- Internal Domainは公開しない。
