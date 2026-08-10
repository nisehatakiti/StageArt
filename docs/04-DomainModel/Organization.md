StageArt Blueprint

Domain Model : Organization

Version : 2.0

⸻

Purpose

Organizationは、舞台芸術活動を行う団体を管理するドメインである。

StageArtにおけるすべての活動はOrganizationを起点として行われる。

Production、Member、Ticket、Budgetなどの情報は、必ずいずれかのOrganizationに所属する。

OrganizationはStageArtのマルチテナントを構成する最も重要なドメインである。

⸻

Concept

Organizationは「劇団」を意味しない。

舞台芸術活動を行うあらゆる団体を表現する。

例）

* 劇団
* プロデュース団体
* ダンスカンパニー
* 学生劇団
* 演劇サークル
* 実行委員会

StageArtは団体種別によって機能を分けない。

⸻

Identity

OrganizationはOrganizationIDによって一意に識別される。

団体名は識別子ではない。

団体名は変更できる。

同名団体が存在しても問題ない。

⸻

Multi Tenant

OrganizationはStageArtにおけるTenantである。

すべてのBusiness DataはOrganizationへ所属する。

異なるOrganization同士でデータを共有してはならない。

Production

Reservation

Budget

Member

Document

ExternalConnection

すべてOrganization単位で管理される。

⸻

Membership

Organizationには複数のPersonが所属できる。

Personは複数Organizationへ所属できる。

所属情報はMembershipによって管理する。

Person
↓
Membership
↓
Organization

Organization自身は所属情報を保持しない。

⸻

Ownership

OrganizationにはOwnerが存在する。

OwnerとはOrganizationを管理できるPersonである。

Owner情報もMembershipのRoleによって管理する。

Organization自身はOwnerIDを保持しない。

⸻

External Connection

Organizationは外部サービスとの接続情報を
ExternalConnectionとして管理する。

ExternalConnectionはOrganizationの子Entityである。

Organization
└── ExternalConnection
       ├── Service
       └── Credential

ExternalConnectionは、
Organizationが外部サービスを利用するための
接続関係を表す。

ExternalConnectionはSNSに限定しない。

例えば、

* X
* Instagram
* Facebook
* YouTube
* TikTok
* LINE
* Google
* Google Drive

などの外部サービスを接続先として扱うことができる。

外部サービスの種類はServiceによって管理する。

⸻

External Connection Responsibility

ExternalConnectionは以下の責務を持つ。

* Organizationと外部Serviceの接続関係を管理する。
* 接続先Serviceを参照する。
* 外部サービス上のAccount情報を管理する。
* 外部サービスへの認証情報をCredentialとして管理する。
* Connection Statusを管理する。
* 接続・切断・認証更新などの状態を管理する。

ExternalConnection自身は、
外部サービス固有のBusiness Logicを持たない。

外部サービスごとの差異はServiceおよび
Infrastructure Layerで吸収する。

⸻

Credential

ExternalConnectionは、
外部サービスへの接続に必要な認証情報をCredentialとして管理する。

CredentialはExternalConnectionに属する。

ExternalConnection
└── Credential

Credentialは、
OAuth、Access Token、Refresh Token、Secretなど、
外部サービスの認証方式に応じた情報を保持する。

認証情報は平文で保存しない。

認証情報の暗号化、Secret管理、Token更新などの
具体的な実装方式はInfrastructure Layerで管理する。

Domain Modelは、
特定の認証方式や外部サービスの実装へ直接依存しない。

⸻

External Account

ExternalConnectionは、
外部サービス上のAccountを識別するための情報を保持する。

例えば、

ExternalConnection
├── Service
├── AccountIdentifier
└── Credential

という構造を基本とする。

AccountIdentifierは、
外部サービス上のユーザー名、アカウントID、
ページIDなど、接続先を識別するための情報を表す。

StageArt内部のAccountとは別の概念である。

StageArtのAccountは、
StageArtへのログインおよび認証を表す。

ExternalConnectionのAccountIdentifierは、
外部サービス上のアカウントを表す。

⸻

Social Media

SNSはExternalConnectionの特別な子Entityとして扱わない。

SNSも外部サービスの一種としてServiceで管理する。

例えば、

Organization
├── ExternalConnection
│   ├── Service = X
│   └── Credential
│
├── ExternalConnection
│   ├── Service = Instagram
│   └── Credential
│
└── ExternalConnection
    ├── Service = Facebook
    └── Credential

という構造を取る。

これにより、
SNS以外の外部サービスについても
同じ接続基盤を利用できる。

⸻

External Connection Scope

ExternalConnectionはOrganizationに所属する。

ExternalConnectionは、
所属Organizationの外部サービス接続としてのみ利用できる。

異なるOrganizationのExternalConnectionを
共有してはならない。

例えば、

Organization A
└── ExternalConnection
       └── Instagram A
Organization B
└── ExternalConnection
       └── Instagram B

というように、
Organizationごとに独立した接続を保持する。

⸻

External Connection Lifecycle

ExternalConnectionは以下の状態を持つ。

* CONNECTED
* DISCONNECTED
* ERROR

CONNECTEDの場合、
外部サービスとの接続が有効である。

DISCONNECTEDの場合、
接続情報は保持されるが、
外部サービスへの操作は実行できない。

ERRORの場合、
認証期限切れなどにより、
再認証が必要な状態を表す。

具体的な状態遷移は、
ExternalConnection Domainで定義する。

⸻

External Service Operation

ExternalConnectionは、
StageArtから外部サービスへ操作するための
接続情報を提供する。

例えばSNSの場合、

Organization
↓
ExternalConnection
↓
Service = X
↓
Credential
↓
X API

という経路で外部サービスへ接続する。

外部サービスへの実際のAPI呼び出しは、
Infrastructure Layerが担当する。

Domain Layerは、
XやInstagramなど特定の外部サービスへ直接依存しない。

⸻

Bulk Publication

ExternalConnectionは、
将来的な外部サービスへの一括投稿を支える基盤となる。

例えば、

Production
↓
Publication
↓
ExternalConnection
├── X
├── Instagram
└── Facebook

という形で、
一つの投稿を複数の外部サービスへ
配信できる構造を想定する。

ただし、
Bulk PublicationそのものはExternalConnectionの責務ではない。

ExternalConnectionは、
外部サービスへの接続を提供する。

一括投稿のBusiness Logicは、
別のDomainとして管理する。

⸻

Lifecycle

Organizationは以下の状態を持つ。

* Active
* Archived
* Deleted

Deletedは論理削除とする。

過去のProductionやAccountingとの整合性を維持する。

OrganizationがArchivedまたはDeletedとなった場合の
ExternalConnectionの扱いは、
LifecycleおよびAuthorizationのルールに従う。

⸻

Automatically Generated

Organization作成時、
StageArtは以下を自動生成する。

* Membership（Owner）
* Default Role
* Default Settings
* Document Space

将来的には

* Homepage
* Public Profile

も生成する。

ExternalConnectionはOrganization作成時に
自動生成しない。

外部サービスとの接続は、
Organization管理者が必要に応じて設定する。

⸻

Public Information

以下は公開できる。

* 団体名
* ロゴ
* 紹介文
* Webサイト
* SNS
* 活動地域

以下は公開しない。

* 内部設定
* メンバー権限
* 会計情報
* 管理情報
* ExternalConnection
* Credential
* 外部サービスの認証情報

SNSなどの公開アカウント情報を公開プロフィールへ
表示する場合も、
ExternalConnectionのCredentialを公開してはならない。

⸻

Authorization

OrganizationのExternalConnectionは、
Organizationを管理する権限を持つ利用者が管理する。

基本的にはOrganization Ownerまたは
適切なOrganization Roleを持つ利用者が、

* ExternalConnectionの追加
* ExternalConnectionの更新
* ExternalConnectionの削除
* 外部サービスへの接続
* 外部サービスからの切断
* 再認証

を実行できる。

ProductionDelegateによるExternalConnection操作については、
Organization Scopeの権限とは別にAuthorizationで定義する。

⸻

Audit Information

ExternalConnectionの管理操作についても、
誰が操作したかを記録できるようにする。

基本的な監査情報として、

* CreatedBy
* CreatedAt
* UpdatedBy
* UpdatedAt

を利用する。

認証情報そのものを監査情報として記録しない。

⸻

Domain Events

ExternalConnectionに関する操作では、
将来的に以下のDomain Eventを利用する。

* ExternalConnectionCreated
* ExternalConnectionUpdated
* ExternalConnectionConnected
* ExternalConnectionDisconnected
* ExternalConnectionError

Credentialの更新やToken更新については、
セキュリティ上の理由から、
SecretそのものをDomain Eventへ含めない。

⸻

Design Decisions

OrganizationはBusiness Dataを所有する。

OrganizationはMemberを保持しない。

OrganizationはRoleを保持しない。

OrganizationはProductionを直接保持しない。

それらは関連ドメインによって管理される。

ExternalConnectionはOrganizationに属する
外部サービス接続の子Entityである。

ExternalConnectionはSNS専用のEntityではない。

SNSはServiceの一種として扱う。

ExternalConnectionはServiceを参照する。

ExternalConnectionはCredentialを保持する。

Credentialは外部サービスの認証情報を表す。

StageArt内部のAccountと、
外部サービスのAccountは別の概念として管理する。

Credentialは平文保存しない。

外部サービスへのAPI呼び出しはInfrastructure Layerが担当する。

ExternalConnectionは特定の外部サービスのAPIへ直接依存しない。

Bulk PublicationはExternalConnectionとは別のDomainとして管理する。

⸻

Future

将来的に追加する。

* Organization Logo
* Brand Color
* Public Homepage
* Fan Club
* Goods Store
* Donation
* Sponsor Management
* External Service Integration
* SNS Bulk Publication
* Publication Scheduling
* External Service Analytics

⸻

Design Principles

* OrganizationはTenantである。
* OrganizationはBusiness DataのOwnerである。
* Personとの関係はMembershipで表現する。
* 権限はRoleが管理する。
* Organization自身は権限を持たない。
* Organizationは舞台芸術活動を行う団体を表現する。
* 同名団体を許可する。
* ExternalConnectionはOrganizationの子Entityである。
* ExternalConnectionは外部サービスとの接続を表す。
* ExternalConnectionはSNSに限定しない。
* Serviceは外部サービスの種類を表す。
* Credentialは外部サービスへの認証情報を表す。
* StageArt内部のAccountと外部サービスのAccountを分離する。
* Credentialは平文で保存しない。
* 外部サービスへの接続処理はInfrastructure Layerで実装する。
* Bulk PublicationはExternalConnectionとは別の責務として管理する。
* Blueprintを唯一の設計基準とする。
