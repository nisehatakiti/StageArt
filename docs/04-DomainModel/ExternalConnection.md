# StageArt Blueprint
# Domain Model : ExternalConnection

Version : 1.0

---

# Purpose

ExternalConnectionは、
Organizationと外部サービスとの接続関係を表す子Entityである。

ExternalConnectionは、
StageArtから外部サービスを利用するための接続情報を管理する。

ExternalConnectionはSNS専用のDomainではない。

SNS、動画サービス、クラウドサービス、メッセージングサービスなど、
StageArtが外部連携するサービスを共通の仕組みで扱う。

---

# Concept

Organizationは複数のExternalConnectionを持つことができる。

    Organization
    └── ExternalConnection
           ├── Service
           └── Credential

ExternalConnectionは、

「このOrganizationが、この外部サービスを利用するための接続」

を表す。

---

# Responsibility

ExternalConnectionは以下を管理する。

- ExternalConnectionId
- Organization
- Service
- AccountIdentifier
- Credential
- Status
- CreatedAt
- CreatedBy
- UpdatedAt
- UpdatedBy

ExternalConnectionは、
外部サービスとの接続状態と接続先を管理する。

外部サービス固有のAPI処理は管理しない。

---

# Identity

ExternalConnectionはExternalConnectionIdによって識別する。

ExternalConnectionIdは変更できない。

ExternalConnectionは必ず一つのOrganizationに所属する。

---

# Organization

ExternalConnectionはOrganizationの子Entityである。

    Organization
    └── ExternalConnection

ExternalConnectionはOrganizationから独立して存在しない。

ExternalConnectionの追加・変更・削除は、
Organizationの管理コンテキストから行う。

異なるOrganization間でExternalConnectionを共有しない。

---

# Service

ExternalConnectionは一つのServiceを参照する。

Serviceは外部サービスの種類を表すMaster Domainである。

例）

- X
- Instagram
- Facebook
- YouTube
- TikTok
- LINE
- Google
- Google Drive

SNSもServiceの一種として扱う。

ExternalConnection自身に
SNS固有の属性を持たせない。

---

# Account Identifier

ExternalConnectionは、
外部サービス上のAccountを識別する情報を保持する。

AccountIdentifierは、
外部サービスのユーザー名、Account ID、Page IDなど、
接続先を識別するための情報を表す。

AccountIdentifierは、
StageArt内部のAccountとは異なる。

StageArt Account
    ↓
StageArtへのログイン

External Account Identifier
    ↓
外部サービス上の接続先

---

# Credential

ExternalConnectionは、
外部サービスへ接続するためのCredentialを保持する。

    ExternalConnection
    └── Credential

Credentialは、
外部サービスの認証方式に応じた認証情報を管理する。

想定される認証方式には、

- OAuth
- Access Token
- Refresh Token
- API Key
- Secret

などがある。

Credentialの具体的な構造は、
Credential Domainで定義する。

---

# Credential Security

Credentialに含まれる認証情報は、
平文で保存してはならない。

Access Token、Refresh Token、Secretなどは、
安全な暗号化・Secret管理機構を利用する。

認証情報そのものを、

- API Response
- Domain Event
- Log
- Audit Log
- Error Message

へ出力してはならない。

具体的な暗号化方式やSecret Storageは、
Infrastructure Layerで管理する。

Domain Modelは、
特定のSecret Management製品へ依存しない。

---

# Authentication

ExternalConnectionは、
Serviceが要求するAuthentication方式に従って接続する。

Authentication方式はServiceによって異なる。

例えば、

    Service
        ↓
    AuthenticationType
        ├── OAuth
        ├── APIKey
        └── Secret

などを想定する。

ExternalConnectionは、
特定のAuthentication方式に直接依存しない。

---

# Connection Status

ExternalConnectionは接続状態を持つ。

基本状態は以下とする。

- CONNECTED
- DISCONNECTED
- ERROR

---

## CONNECTED

外部サービスとの接続が有効な状態。

StageArtから許可された外部操作を実行できる。

---

## DISCONNECTED

外部サービスとの接続を切断した状態。

ExternalConnection自体は保持する。

Credentialも必要に応じて保持するが、
外部サービスへの操作は実行できない。

---

## ERROR

外部サービスとの接続に問題が発生している状態。

例）

- Access Token失効
- Refresh Token失効
- API認証失敗
- External Service Error

ERRORの場合、
必要に応じて再認証を要求する。

---

# Connection Lifecycle

基本的なLifecycleは以下とする。

    Created
       ↓
    CONNECTED
       ↓
    DISCONNECTED
       ↓
    CONNECTED

認証エラーが発生した場合、

    CONNECTED
       ↓
    ERROR
       ↓
    CONNECTED

または、

    ERROR
       ↓
    DISCONNECTED

などの状態遷移を許可する。

具体的な状態遷移ルールは、
ServiceおよびInfrastructureの実装仕様で定義する。

---

# Create

ExternalConnectionは、
Organizationに対する外部サービス接続を作成するときに生成する。

作成時に以下を指定する。

- Organization
- Service
- AccountIdentifier
- Credential

作成者をCreatedByへ記録する。

作成日時をCreatedAtへ記録する。

---

# Update

ExternalConnectionは、
Organizationの管理権限を持つ利用者によって更新できる。

更新可能な情報には、

- Service
- AccountIdentifier
- Credential
- Status

がある。

ExternalConnectionIdは変更できない。

Service変更については、
接続中のCredentialとの整合性を確認する。

必要に応じて再認証を要求する。

---

# Disconnect

ExternalConnectionは、
外部サービスとの接続を切断できる。

切断すると、

Status
    = DISCONNECTED

とする。

ExternalConnectionそのものは削除しなくてもよい。

これにより、
後から再接続できる。

---

# Reconnect

DISCONNECTEDまたはERRORのExternalConnectionは、
再認証によって再接続できる。

再接続に成功すると、

Status
    = CONNECTED

とする。

Credentialが更新された場合は、
UpdatedByおよびUpdatedAtを更新する。

---

# Delete

ExternalConnectionは、
Organizationから削除できる。

削除後は、
そのExternalConnectionを利用した外部操作を実行できない。

削除されたExternalConnectionに紐づく
Credentialの安全な破棄も行う。

Credentialの削除処理は、
Infrastructure Layerが担当する。

---

# Authorization

ExternalConnectionはOrganization ScopeのResourceである。

基本的には、
Organizationを管理する権限を持つ利用者だけが操作できる。

必要に応じてDelegateRoleへ、

- ExternalConnection.Read
- ExternalConnection.Create
- ExternalConnection.Update
- ExternalConnection.Delete
- ExternalConnection.Connect
- ExternalConnection.Disconnect

などのPermissionを追加できる。

Authorizationの具体的なルールは、
Authorization Domainで定義する。

---

# External Service Operation

ExternalConnectionは、
外部サービスへの接続情報を提供する。

実際のAPI呼び出しはInfrastructure Layerが担当する。

例えば、

    Application
        ↓
    ExternalConnection
        ↓
    Service
        ↓
    Infrastructure Adapter
        ↓
    External Service API

という構造を取る。

Domain Layerから
X API、Instagram API、YouTube APIなどを
直接呼び出してはならない。

---

# Service Adapter

外部サービスごとのAPI仕様差異は、
Infrastructure LayerのAdapterで吸収する。

例えば、

    Infrastructure
    ├── XAdapter
    ├── InstagramAdapter
    ├── FacebookAdapter
    └── YouTubeAdapter

などを実装できる。

ExternalConnection Domainは、
これらの具体的なAdapterを意識しない。

---

# Bulk Publication

ExternalConnectionは、
将来的な一括投稿機能の接続基盤として利用する。

例えば、

    Publication
        ↓
    ExternalConnection
        ├── X
        ├── Instagram
        └── Facebook

という形で、
一つのPublicationを複数の外部サービスへ送信できる。

ただし、
一括投稿そのものはExternalConnectionの責務ではない。

ExternalConnectionは
「外部サービスへ接続できる状態」を提供する。

投稿内容、
投稿対象、
投稿日時、
投稿結果などは、
別のPublication Domainで管理する。

---

# Audit Information

ExternalConnectionは監査情報を保持する。

CreatedBy

ExternalConnectionを作成した利用者。

CreatedAt

ExternalConnectionを作成した日時。

UpdatedBy

ExternalConnectionを最後に変更した利用者。

UpdatedAt

ExternalConnectionを最後に変更した日時。

Credentialそのものを
監査情報として保存しない。

---

# Domain Events

ExternalConnectionは以下のDomain Eventを利用する。

- ExternalConnectionCreated
- ExternalConnectionUpdated
- ExternalConnectionConnected
- ExternalConnectionDisconnected
- ExternalConnectionError

Domain Eventには、
認証情報やSecretを含めない。

---

# History

ExternalConnectionは、
Personの活動Historyを生成しない。

ExternalConnectionは
外部サービスとの技術的な接続を表す。

外部サービスへの投稿などのBusiness Activityは、
Publicationなどの別Domainで管理する。

---

# Business Rules

- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionは必ず一つのOrganizationに所属する。
- ExternalConnectionは一つのServiceを参照する。
- ExternalConnectionはCredentialを保持する。
- SNSはExternalConnectionの特別なEntityではない。
- SNSはServiceの一種として扱う。
- ExternalConnectionはStageArt内部のAccountとは別の概念である。
- AccountIdentifierは外部サービス上の接続先を識別する。
- Credentialは外部サービスへの認証情報を表す。
- Credentialは平文で保存しない。
- CredentialをAPI Responseに含めない。
- CredentialをDomain Eventに含めない。
- CredentialをLogへ出力しない。
- ExternalConnectionはCONNECTED、DISCONNECTED、ERRORの状態を持つ。
- DISCONNECTEDでもExternalConnection自体は保持できる。
- ERRORの場合は必要に応じて再認証を要求する。
- ExternalConnectionはOrganization Scopeで認可する。
- 外部サービスへのAPI呼び出しはInfrastructure Layerが担当する。
- 外部サービスごとの差異はAdapterで吸収する。
- ExternalConnectionは特定の外部サービスAPIへ直接依存しない。
- Bulk PublicationはExternalConnectionとは別のDomainで管理する。
- ExternalConnectionはPersonの活動Historyを生成しない。
- Credentialの具体的なSecret管理はInfrastructure Layerが担当する。

---

# Design Decisions

ExternalConnectionはSNS専用Domainとして設計しない。

SNSを含むすべての外部サービスを、
Serviceという共通概念によって扱う。

Organizationは複数のExternalConnectionを持つことができる。

ExternalConnectionはOrganizationの子Entityとして管理する。

ExternalConnectionはServiceとCredentialを参照する。

StageArt内部のAccountと、
外部サービスのAccountを分離する。

外部サービスの認証情報はCredentialとして抽象化する。

Credentialの安全な保存はInfrastructure Layerの責務とする。

外部サービスへのAPI呼び出しはInfrastructure Adapterを経由する。

一括投稿機能はExternalConnectionとは分離する。

---

# Future

将来的に以下へ対応する。

- OAuth連携
- Token自動更新
- API Key認証
- Secret認証
- External Service Health Check
- Connection Expiration
- Reauthorization
- Bulk Publication
- Scheduled Publication
- Publication Result
- External Service Analytics

---

# Design Principles

- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionは外部サービスとの接続を表す。
- ExternalConnectionはSNSに限定しない。
- Serviceは外部サービスの種類を表す。
- Credentialは外部サービスの認証情報を表す。
- StageArt内部のAccountと外部サービスのAccountを分離する。
- Credentialは平文で保存しない。
- Secret情報をAPI、Event、Logへ漏洩させない。
- 外部サービス固有の処理はInfrastructure Layerへ隔離する。
- ExternalConnection Domainは特定の外部サービスAPIへ依存しない。
- Bulk PublicationはExternalConnectionとは別の責務とする。
- Business RuleはDomain Layerが管理する。
- APIはApplication Layerの公開インターフェースとして機能する。
