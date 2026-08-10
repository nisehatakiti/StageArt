# StageArt Blueprint
# Domain Model : Credential

Version : 1.0

---

# Purpose

Credentialは、
ExternalConnectionが外部サービスへ接続するために必要とする
認証情報を表すDomainである。

CredentialはExternalConnectionに属する。

    Organization
    └── ExternalConnection
           ├── Service
           └── Credential

Credentialは、
StageArt内部のAccountとは異なる。

StageArt Accountは、
StageArtへのログインおよび認証を表す。

Credentialは、
外部サービスへの接続および認証を表す。

---

# Concept

Credentialは、
外部サービスが要求する認証方式を抽象化する。

外部サービスによって認証方式は異なる。

例えば、

    OAuth
    API Key
    Secret
    Access Token
    Refresh Token

などを利用する。

Credentialは、
これらの認証情報をExternalConnectionから
利用できる形で管理する。

---

# Responsibility

Credentialは以下の責務を持つ。

- ExternalConnectionへの所属
- AuthenticationType
- 認証情報
- Credential Status
- 認証情報の有効性
- CreatedAt
- CreatedBy
- UpdatedAt
- UpdatedBy

Credentialは、
外部サービスへのAPI処理そのものを担当しない。

---

# Ownership

CredentialはExternalConnectionに属する。

Credentialは独立したBusiness Resourceとして扱わない。

    ExternalConnection
    └── Credential

CredentialはExternalConnectionを経由してのみ
作成・更新・削除できる。

Credential単独の公開APIは提供しない。

---

# Identity

CredentialはCredentialIdによって識別する。

CredentialIdは変更できない。

Credentialは必ず一つのExternalConnectionに所属する。

CredentialをOrganization間で共有してはならない。

---

# Authentication Type

Credentialは、
Serviceが要求するAuthenticationTypeに応じて
認証情報を保持する。

AuthenticationTypeはServiceによって定義される。

基本的には以下を想定する。

- OAUTH
- API_KEY
- SECRET
- NONE

AuthenticationTypeそのものはCredentialが管理するMasterではなく、
ServiceのAuthenticationTypeを参照する。

---

# OAuth

OAuthを利用するServiceでは、
CredentialはOAuth認証情報を保持する。

想定される情報は、

- AccessToken
- RefreshToken
- TokenExpiresAt
- Scope

などである。

AccessTokenおよびRefreshTokenは、
安全に保存する。

TokenそのものをAPI Response、
Domain Event、Logへ出力してはならない。

---

# Access Token

AccessTokenは、
外部サービスAPIへのアクセスに利用する認証情報である。

AccessTokenはSecret情報として扱う。

平文で永続化してはならない。

AccessTokenの取得、
更新、
失効処理はInfrastructure Layerが担当する。

Credential Domainは、
AccessTokenを利用するための状態を管理する。

---

# Refresh Token

RefreshTokenは、
AccessTokenを更新するための認証情報である。

RefreshTokenはAccessTokenよりも
長期間利用される場合がある。

RefreshTokenもSecret情報として扱う。

平文で永続化してはならない。

RefreshTokenの更新処理は、
Infrastructure Layerが担当する。

---

# Token Expiration

OAuth Credentialでは、
AccessTokenの有効期限を管理できる。

TokenExpiresAtを利用して、
AccessTokenの期限を判断する。

AccessTokenが期限切れとなった場合は、
RefreshTokenによる更新を試行する。

更新に失敗した場合、
ExternalConnectionをERROR状態へ変更する。

---

# API Key

ServiceがAPI Key認証を利用する場合、
CredentialはAPI Keyを保持する。

API KeyはSecret情報として扱う。

API Keyを、

- API Response
- Domain Event
- Log
- Audit Log
- Error Message

へ出力してはならない。

---

# Secret

ServiceがSecretによる認証を要求する場合、
CredentialはSecretを保持する。

Secretには、
パスワード、Client Secret、Signing Secretなど、
外部サービスへの接続に必要な秘密情報を含めることができる。

Secretの具体的な意味はServiceおよび
Infrastructure Adapterによって決定する。

---

# Security

Credentialに含まれるすべての認証情報は、
Secret情報として扱う。

以下の情報を平文で保存してはならない。

- AccessToken
- RefreshToken
- APIKey
- Secret
- ClientSecret
- Password

認証情報は、
暗号化または安全なSecret Management機構によって
保護する。

具体的な暗号化方式およびSecret Storageは、
Infrastructure Layerで管理する。

Domain Modelは、
特定のSecret Management製品へ依存しない。

---

# Read Access

CredentialのSecret値は、
通常のResource取得処理では返却しない。

例えば、

    GET /external-connections/{id}

のResponseには、

- Service
- AccountIdentifier
- ConnectionStatus
- CredentialStatus

などの安全な情報のみを返却する。

AccessTokenやRefreshTokenなどの
Secret値は返却しない。

---

# Write Access

Credentialの作成・更新は、
ExternalConnectionの管理権限を持つ利用者が実行する。

Credential単独の更新APIは提供しない。

認証情報を更新する場合は、
ExternalConnectionを経由して更新する。

---

# Credential Status

Credentialは状態を持つ。

基本状態は以下とする。

- ACTIVE
- EXPIRED
- INVALID
- REVOKED

---

## ACTIVE

認証情報が有効であり、
外部サービスへの接続に利用できる状態。

---

## EXPIRED

認証情報の有効期限が切れている状態。

OAuthの場合は、
AccessTokenの期限切れなどが該当する。

Refresh Tokenによって更新できる場合は、
Infrastructure Layerが更新を試行する。

---

## INVALID

認証情報が外部サービスによって
無効と判断された状態。

再認証が必要になる場合がある。

---

## REVOKED

認証情報が明示的に無効化された状態。

External Service側またはStageArt側で
認証を取り消した場合などが該当する。

---

# Credential Lifecycle

基本的なLifecycleは以下とする。

    Created
       ↓
    ACTIVE
       ↓
    EXPIRED
       ↓
    ACTIVE

認証情報が無効になった場合、

    ACTIVE
       ↓
    INVALID
       ↓
    ACTIVE

または、

    INVALID
       ↓
    REVOKED

などの状態遷移を許可する。

具体的な状態遷移はAuthenticationTypeおよび
Infrastructure Layerの仕様に従う。

---

# Credential Rotation

Credentialは将来的に
認証情報のローテーションに対応できる構造とする。

例えば、

    Current Credential
          ↓
    New Credential
          ↓
    Validation
          ↓
    Activation
          ↓
    Old Credential Revocation

という流れを想定する。

Credentialのローテーションによって、
ExternalConnectionそのものを作り直す必要はない。

---

# ExternalConnection Relationship

CredentialはExternalConnectionの
認証情報として利用される。

    Organization
        ↓
    ExternalConnection
        ├── Service
        └── Credential

ExternalConnectionはServiceを参照し、
Serviceが要求するAuthenticationTypeに応じて
Credentialを利用する。

---

# Service Relationship

CredentialはServiceへ直接所属しない。

ServiceはMaster Domainとして
外部サービスの種類を管理する。

CredentialはExternalConnectionに所属し、
ExternalConnectionがServiceを参照する。

したがって、

    Service
        ↑
    ExternalConnection
        ↓
    Credential

という関係になる。

---

# Infrastructure

CredentialのSecret値を実際に取得、
保存、復号、利用する処理は
Infrastructure Layerが担当する。

例えば、

    Application
        ↓
    ExternalConnection
        ↓
    Credential
        ↓
    Credential Provider
        ↓
    Secret Storage
        ↓
    External Service

という構造を想定する。

Domain Layerから
Secret Storage製品や外部APIを直接呼び出してはならない。

---

# Credential Provider

Credential Providerは、
CredentialのSecret情報を安全に扱うための
Infrastructure Adapterである。

具体的な実装は、

- WordPress Secret Storage
- Environment Variable
- Encrypted Database
- External Secret Manager

などから選択できる。

具体的な方式はInfrastructure設計で決定する。

---

# Logging

CredentialのSecret値をLogへ出力してはならない。

以下のような値も、
必要以上にLogへ出力しない。

- AccessToken
- RefreshToken
- APIKey
- Secret
- ClientSecret

認証処理の失敗を記録する場合は、
Secret値を含まないエラー情報のみ記録する。

---

# Domain Events

Credentialに関するDomain Eventを利用する場合、
Secret値をEvent Payloadへ含めない。

例えば、

- CredentialCreated
- CredentialUpdated
- CredentialExpired
- CredentialInvalid
- CredentialRevoked

などを利用できる。

ただし、
認証情報の更新そのものが
Business Eventとして必要かどうかは、
Event設計で判断する。

---

# Audit Information

Credentialの管理操作については、
監査情報を保持できる。

基本的な監査情報として、

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

を利用する。

監査情報には、
CredentialのSecret値を含めない。

---

# Delete

CredentialはExternalConnectionの削除または
認証情報の切断に伴って削除できる。

Credentialを削除する場合は、
Secret Storage上の認証情報も安全に破棄する。

Credentialの物理的なSecret削除は
Infrastructure Layerが担当する。

---

# Authorization

Credentialへのアクセス権限は、
ExternalConnectionのAuthorizationに従う。

利用者がCredentialを取得する権限を持っていても、
Secret値そのものを読み出せるとは限らない。

CredentialのSecret値は、
原則として利用者へ返却しない。

---

# History

CredentialはPersonの活動Historyを生成しない。

Credentialは技術的な認証情報を表す。

外部サービスへの投稿、
動画公開、
ファイルアップロードなどの
Business Activityは別Domainで管理する。

---

# Business Rules

- CredentialはExternalConnectionに属する。
- Credentialは単独のBusiness Resourceとして公開しない。
- CredentialはExternalConnectionを経由して操作する。
- CredentialはServiceに直接所属しない。
- AuthenticationTypeはServiceによって決定される。
- OAuth認証ではAccessTokenとRefreshTokenを利用できる。
- AccessTokenはSecret情報として扱う。
- RefreshTokenはSecret情報として扱う。
- APIKeyはSecret情報として扱う。
- SecretはSecret情報として扱う。
- 認証情報を平文で保存しない。
- 認証情報をAPI Responseへ返却しない。
- 認証情報をDomain Eventへ含めない。
- 認証情報をLogへ出力しない。
- 認証情報をAudit Logへ出力しない。
- CredentialはACTIVE、EXPIRED、INVALID、REVOKEDの状態を持つ。
- Token更新処理はInfrastructure Layerが担当する。
- Secret StorageはInfrastructure Layerが担当する。
- Credential ProviderはInfrastructure Layerに実装する。
- CredentialのSecret値を利用者へ直接公開しない。
- Credentialの削除時はSecret情報も安全に破棄する。
- CredentialはPersonの活動Historyを生成しない。

---

# Design Decisions

Credentialは、
外部サービスの認証方式を共通化するための
Domainとして定義する。

ExternalConnectionは、
ServiceとCredentialを組み合わせることで、
外部サービスへの接続を表現する。

CredentialはServiceに直接所属させない。

CredentialはExternalConnectionに所属させる。

これにより、
同じServiceであってもOrganizationごとに
異なるCredentialを安全に保持できる。

StageArt内部のAccountと、
External ServiceのCredentialを完全に分離する。

認証情報の安全な保存および利用は
Infrastructure Layerに隔離する。

Domain Modelは、
特定の認証プロバイダー、
Secret Management製品、
外部サービスAPIへ依存しない。

---

# Future

将来的に以下へ対応する。

- OAuth 2.0
- OAuth 2.1
- PKCE
- Token Rotation
- Refresh Token Rotation
- Credential Rotation
- Secret Manager
- Hardware-backed Secret Storage
- Credential Health Check
- Automatic Reauthorization
- Multiple Credential Support
- Service-specific Authentication
- Credential Expiration Notification

---

# Design Principles

- CredentialはExternalConnectionに属する。
- Credentialは外部サービスの認証情報を表す。
- Credentialは単独の公開Resourceにしない。
- CredentialはExternalConnectionを経由して操作する。
- CredentialはServiceに直接所属しない。
- StageArt内部のAccountとExternal Credentialを分離する。
- Secret情報は平文で保存しない。
- Secret情報をAPI、Event、Log、Audit Logへ漏洩させない。
- Secret StorageはInfrastructure Layerへ隔離する。
- Authentication処理はInfrastructure Layerへ隔離する。
- Credential Domainは特定の外部サービスへ依存しない。
- Credential Domainは特定のSecret Management製品へ依存しない。
- Business RuleはDomain Layerが管理する。
- APIはApplication Layerの公開インターフェースとして機能する。
