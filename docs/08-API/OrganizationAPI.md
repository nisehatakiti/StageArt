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

実装済みのロール（オーナー・代理人・一般メンバー）と代理人の管理領域による認可は、
「実装済みAPI: 代理人・参加コード・参加申請」を参照する。

---

# 実装済みAPI: 代理人・参加コード・参加申請

この節は、現在のWordPressプラグイン実装（`plugin/src/Presentation/Rest/`）のルート定義と一致させた仕様である。
上記の `/api/v1/...` はBlueprint上の表記であり、実装のベースURLは次のとおり。

```text
/wp-json/stageart/v1
```

リクエスト・レスポンスはJSON。日時はISO 8601（`DATE_ATOM`）。

## 認証

すべてのエンドポイントはログインを必須とする（`permission_callback` = `is_user_logged_in()`）。

未ログインの場合はWordPress標準の `401 rest_forbidden` を返し、処理を実行しない。

ログインしていても、操作ごとの認可はサーバー側のUseCaseが、DBに保存されたMembershipと代理人の管理領域から判定する。
クライアントから送られたロールや権限値は判定に使用しない。

## ロールと管理領域

| 区分 | 判定 |
|---|---|
| オーナー | Membershipの `role_key = OWNER`（ACTIVE）。全管理領域を持つ。 |
| 代理人 | `role_key = ORGANIZATION_MANAGER`（ACTIVE）。オーナーが付与した管理領域だけを持つ。 |
| 一般メンバー | `role_key = MEMBER`。管理領域を持たない。 |
| 団体外ユーザー | 対象OrganizationのACTIVEなMembershipがない。管理領域を持たない。 |

管理領域（`DelegateArea`）は `MEMBERS`（メンバー管理）、`PRODUCTIONS`（公演管理）、`ACCOUNTING`（会計管理）の3つ。

管理領域は代理人のMembershipごとに `stageart_membership_delegate_areas` へ保存する。
代理人を解除する、または代表者交代で代理人がオーナーになると、その人の管理領域は削除される。

`GET /organizations` / `GET /organizations/{id}` などのOrganizationレスポンスには、ログイン中の本人の管理領域 `current_person_management_areas`（文字列配列）が含まれる。
これは画面表示用であり、APIの認可はこの値を使わずにサーバー側で再判定する。

## 権限表

| 操作 | オーナー | 代理人（MEMBERSあり） | 代理人（MEMBERSなし） | 一般メンバー | 団体外 |
|---|---|---|---|---|---|
| 団体の参加コード 一覧・発行・無効化 | ○ | ○ | × | × | × |
| 参加申請 一覧・承認・却下 | ○ | ○ | × | × | × |
| 代理人の設定・解除 | ○ | × | × | × | × |
| 代理人の管理領域 一覧・変更 | ○ | × | × | × | × |

代理人は自分自身を含め、管理領域を変更できない。

## 公演単位の権限との関係

公演（Production）の参加コードは、団体の権限とは独立してProduction Scopeで判定する。

- 一覧・発行・無効化は、そのProductionの主担当者（PrimaryManager）のみ。
- 団体のオーナー、MEMBERSを含む全管理領域を持つ代理人、Productionの代理人（PARTICIPANT_MANAGER + REHEARSAL_MANAGER）であっても、主担当者でなければ拒否する。
- 団体のメンバー管理権限は、公演の参加コードに対して何の権限も与えない。

| 操作 | 主担当者 | それ以外（団体オーナー・団体代理人・公演代理人・一般メンバー・団体外） |
|---|---|---|
| 公演の参加コード 一覧・発行・無効化 | ○ | × |

## 団体の参加コード

### GET /organizations/{id}/join-keys

対象Organizationに発行された参加コードを、状態を問わずすべて返す。

一覧に含まれるコードはそのまま参加申請に使えるため、発行と同じ認可（オーナー、またはMEMBERSを持つ代理人）を必須とする。

Response `200 OK`

```json
[
  {
    "id": "…",
    "code": "ABCD1234",
    "target_type": "ORGANIZATION",
    "target_id": "{id}",
    "status": "ACTIVE",
    "expires_at": null,
    "max_uses": null,
    "use_count": 0
  }
]
```

`status` は `ACTIVE` / `DISABLED` / `EXPIRED` / `EXHAUSTED`。

Errors

- `403 stageart_organization_access_denied` — 権限がない（団体外・一般メンバー・MEMBERSを持たない代理人、またはPersonが紐付いていない）。
- `404 stageart_organization_not_found` — 権限確認後、Organizationが存在しない。

権限確認はOrganizationの存在確認より先に行うため、Membershipを持たないOrganizationのIDは存在の有無にかかわらず403になる。

### POST /organizations/{id}/join-keys

参加コードを新規発行する。リクエストボディは不要。

Response `201 Created` — 上記一覧の要素と同じ形式の1件。

Errors は一覧と同じ（`403 stageart_organization_access_denied` / `404 stageart_organization_not_found`）。

### POST /join-keys/{joinKeyId}/disable

参加コードを無効化する（`status` → `DISABLED`）。リクエストボディは不要。

認可はコード自身の対象で判定する。団体のコードは、そのOrganizationのオーナーまたはMEMBERSを持つ代理人。公演のコードは、そのProductionの主担当者。
別のOrganization・ProductionのコードIDを指定しても無効化できない。

無効化しても、そのコードで作成済みのMembership / Participantは削除しない。

Response `200 OK` — 無効化後の参加コード1件（一覧の要素と同じ形式）。

Errors

- `403 stageart_join_key_access_denied` — 権限がない。
- `404 stageart_join_key_not_found` — コードが存在しない。

### POST /join-keys/resolve

参加コードから参加先を確認する（参加申請前の確認画面用）。ログインしていれば誰でも呼べる。コード自体を知っている人だけが使える。

Request

```json
{ "code": "ABCD-1234" }
```

ハイフンと大文字・小文字の違いは無視する。

Response `200 OK`

```json
{ "join_key_id": "…", "target_type": "ORGANIZATION", "target_id": "…", "target_name": "…", "target_slug": "…" }
```

Errors

- `404 stageart_join_key_not_found` — 存在しない、または使用できない（無効化・期限切れ・上限到達）コード。

## 参加申請

### POST /membership-requests

本人がOrganizationへの参加を申請する（`status` = `REQUESTED`）。

Request（どちらか一方）

```json
{ "join_key_code": "ABCD1234" }
```

```json
{ "organization_id": "…" }
```

`organization_id` で申請できるのは公開済みのOrganizationのみ。

Response `201 Created` — 参加申請1件（下記の形式）。

Errors

- `404 stageart_join_key_not_found` — 使用できない参加コード、または団体用でないコード。
- `404 stageart_organization_not_found` — Organizationが存在しない、または（`organization_id` 指定時）非公開。
- `409 stageart_membership_already_exists` — すでにACTIVEまたは申請中。
- `422 stageart_membership_request_invalid` — どちらも指定されていない。
- `403 stageart_membership_access_denied` — Personが紐付いていない。

### GET /organizations/{id}/membership-requests

申請中（`REQUESTED`）の参加申請を返す。オーナー、またはMEMBERSを持つ代理人のみ。

Response `200 OK`

```json
[
  {
    "id": "…",
    "organization_id": "{id}",
    "person_id": "…",
    "person_family_name": "山田",
    "person_given_name": "太郎",
    "status": "REQUESTED",
    "requested_at": "2026-08-20T10:00:00+09:00",
    "joined_at": null
  }
]
```

Errors

- `403 stageart_membership_access_denied` — 権限がない。

### POST /membership-requests/{membershipId}/approve

参加申請を承認する（`REQUESTED` → `ACTIVE`、`joined_at` を記録）。承認後のロールは一般メンバー（`MEMBER`）。リクエストボディは不要。

認可は申請自身のOrganizationで判定する。オーナー、またはMEMBERSを持つ代理人のみ。
別のOrganizationの申請IDを指定しても承認できない。

Response `200 OK` — 承認後の参加申請1件（上記の形式、`status` = `ACTIVE`）。

Errors

- `403 stageart_membership_access_denied` — 権限がない（別Organizationの申請を含む）。
- `404 stageart_membership_request_not_found` — 申請が存在しない。
- `422 stageart_membership_request_invalid` — 申請中ではない（承認済み・却下済み）。

### POST /membership-requests/{membershipId}/reject

参加申請を却下する（`REQUESTED` → `REJECTED`）。認可・レスポンス・エラーは承認と同じ。

## 代理人

### POST /organizations/{id}/delegates

OrganizationのACTIVEなメンバー（オーナー以外）を代理人にする。オーナーのみ。新しい代理人の管理領域は空で始まる。

Request

```json
{ "person_id": "…" }
```

Response `204 No Content`

Errors

- `403 stageart_organization_access_denied` — オーナーではない。
- `422 stageart_organization_invalid` — 対象がこのOrganizationのACTIVEなメンバーではない、またはオーナー本人。

### DELETE /organizations/{id}/delegates/{personId}

代理人を解除して一般メンバーに戻す。オーナーのみ。その人の管理領域は削除される。

Response `204 No Content`

Errors

- `403 stageart_organization_access_denied` — オーナーではない。
- `422 stageart_organization_invalid` — 対象がこのOrganizationのACTIVEなメンバーではない、または代理人ではない。

### GET /organizations/{id}/delegates

全代理人（ACTIVEな `ORGANIZATION_MANAGER`）の管理領域を返す。オーナーのみ。

Response `200 OK`

```json
[ { "person_id": "…", "areas": ["MEMBERS", "PRODUCTIONS"] } ]
```

Errors

- `403 stageart_organization_access_denied` — オーナーではない。

### PUT /organizations/{id}/delegates/{personId}/areas

1人の代理人の管理領域をまとめて置き換える。オーナーのみ。

Request

```json
{ "areas": ["MEMBERS", "ACCOUNTING"] }
```

空配列はすべての管理領域を外す。

Response `200 OK`

```json
{ "person_id": "…", "areas": ["MEMBERS", "ACCOUNTING"] }
```

`areas` は表示順（MEMBERS → PRODUCTIONS → ACCOUNTING）で返る。

Errors

- `403 stageart_organization_access_denied` — オーナーではない（代理人が自分の管理領域を変更する場合を含む）。
- `422 stageart_organization_invalid` — `areas` が配列でない、未知の管理領域・重複を含む、または対象がこのOrganizationのACTIVEな代理人ではない。

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
