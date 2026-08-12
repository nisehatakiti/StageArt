# StageArt Blueprint

# Domain Model : Organization

Version : 3.2

---

# Purpose

Organizationは、
舞台芸術活動を行う団体を表すDomainである。

OrganizationはStageArtにおけるTenantであり、
団体に属するBusiness Dataの管理単位となる。

Organizationは「劇団」を意味するものではない。

舞台芸術活動を行うあらゆる団体を表現する。

---

# Concept

Organizationの例：

- 劇団
- プロデュース団体
- ダンスカンパニー
- 学生劇団
- 演劇サークル
- 実行委員会
- 制作団体
- その他舞台芸術団体

StageArtは、
Organizationの種別によって基本的なDomain構造を分けない。

---

# Identity

OrganizationはOrganizationIdによって一意に識別される。

OrganizationIdは変更できない。

団体名は識別子ではない。

団体名は変更できる。

同名のOrganizationが存在しても問題ない。

---

# Multi Tenant

OrganizationはStageArtにおけるTenantである。

Organizationに属するBusiness Dataは、
Organization Scopeの中で管理する。

異なるOrganizationの内部情報へ、
権限なくアクセスしてはならない。

Organization Scopeの対象には、

- Project
- Membership
- Accounting
- Equipment
- Document
- External Connection
- Regulation
- Announcement

などが含まれる。

ProductionはProjectを介してOrganizationに属する。

ReservationやParticipantなどのProduction関連Domainについても、
所属するProductionを通じてOrganization Scopeを判定する。

---

# Membership

Organizationには複数のPersonが所属できる。

Personは複数のOrganizationへ所属できる。

所属関係はMembershipによって管理する。

基本構造：

Person
    ↓
Membership
    ↓
Organization

Organization自身は、
Personを直接保持しない。

Membershipは、

- 所属状態
- Organization内のRole
- 所属開始
- 所属終了
- その他所属に関する情報

を管理する。

Membershipの詳細なLifecycleは、
Membership Domainで定義する。

---

# Role

Organization内におけるPersonの管理・運営上の権限は、
Membershipに関連するRoleによって管理する。

RoleはPerson自身の属性ではない。

同じPersonでも、
Organizationごとに異なるRoleを持つことができる。

例：

Person A
    │
    ├── Membership
    │      └── Organization A
    │             └── Role = Administrator
    │
    └── Membership
           └── Organization B
                  └── Role = Rehearsal Manager

RoleはOrganization Scopeにおける
Permission Setを表す。

Organization自身がRoleをPersonの属性として保持するのではなく、
Membershipを通じてPersonへ適用する。

Roleの定義およびPermission Setは、
Role Domainで管理する。

---

# Role and Participant Type

RoleとParticipant Typeは、
異なる概念として扱う。

Role：

OrganizationまたはProductionにおける
管理・運営上の権限を表す。

Participant Type：

Productionにおける
参加区分を表す。

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role

一方、

Production
    ↓
Participant
    ↓
Participant Type

となる。

Participant Typeの例：

- CAST
- STAFF

Participant Typeによって、
Organizationの管理権限を付与してはならない。

Roleによって、
Productionへの参加区分を自動的に決定してはならない。

---

# Organization Owner

Organizationには、
Organizationを管理するOwnerが存在する。

OwnerはOrganizationに対する管理権限を持つPersonである。

Owner情報はMembershipおよびRoleによって表現し、
Organization自身にOwnerIdを直接保持しない。

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role

Ownerは、
Organization Scopeにおける全管理権限を持つ。

Ownerを変更する場合も、
Membershipに適用されているRoleの変更として管理できる。

Organization Ownerという独立したRole体系は作成しない。

Ownerに必要な具体的なRoleとPermissionは、
Role DomainおよびAuthorization Domainで定義する。

---

# Organization Administration

Organizationの管理権限は、
MembershipとRoleによって管理する。

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

Organizationの管理権限を他のPersonへ委任する場合も、
別のDelegateRoleを作成しない。

対象PersonのMembershipに、
必要なRoleを適用する。

例えば、

Person A
    ↓
Membership
    ↓
Organization A
    ↓
Role = Administrator

Person B
    ↓
Membership
    ↓
Organization A
    ↓
Role = Rehearsal Manager

のように、
Personごとに必要なRoleを適用できる。

---

# Organization Role Scope

Roleは、
Organization ScopeにおいてMembershipを通じて適用する。

RoleそのものはOrganizationIdを保持しない。

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

Role Definitionは、
Role Domainで共通管理する。

同じRole Definitionを、
複数のOrganizationで利用できる。

OrganizationごとにRole Definitionを複製する必要はない。

---

# Production Delegate

Production単位の権限は、
Organization ScopeのMembershipとは別に管理する。

基本構造：

Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

ProductionDelegateは、
特定Productionの管理権限をPersonへ適用する。

ProductionDelegateは、
Organization全体のRoleを変更しない。

ProductionDelegateによって、
Organization全体の権限を与えずに、
特定Productionのみを管理できる。

ProductionDelegateの詳細な構造は、
ProductionDelegate Domainで定義する。

---

# Organization Scope and Production Scope

Organization ScopeとProduction Scopeは、
明確に区別する。

Organization Scope：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

Production Scope：

Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

Role Definitionは共通とする。

Organization Scope専用のDelegateRoleや、
Production Scope専用の別Role体系は作成しない。

---

# RoleAssignment

Organization Domainでは、
RoleAssignmentという独立Domainを作成しない。

RoleがPersonへ適用される関係は、
Membershipによって表現する。

Organization Scope：

Person
    ↓
Membership
    ↓
Role

Production Scope：

Person
    ↓
ProductionDelegate
    ↓
Role

RoleAssignmentという中間Domainを追加して、
Role適用を別管理する構造にはしない。

---

# DelegateRole

DelegateRoleという独立したRole体系は使用しない。

Organizationの管理権限を委任する場合も、
通常のRole Definitionを使用する。

例えば、

Person
    ↓
Membership
    ↓
Organization
    ↓
Role = Rehearsal Manager

のように、
必要なPermission Setを持つRoleを
Membershipへ適用する。

Delegateであること自体を表すために、
別のRole Definitionを作成しない。

---

# Project

OrganizationはProjectを保持する。

基本構造は、

Organization
    ↓
Project
    ↓
Production

とする。

Projectは、
Organizationが行う活動・制作の内部単位である。

Projectは一つ以上のProductionを持つことができる。

Projectは利用者が必ずしも意識する必要のないInternal Domainであり、
UI上では必要に応じてStageArtが適切な名称・表示方法で扱う。

---

# Production

ProductionはProjectに所属する。

Productionは、
具体的な公演・活動を表す。

OrganizationがProductionを直接所有するのではなく、

Organization
    ↓
Project
    ↓
Production

という階層で管理する。

Productionに関連する、

- Participant
- Performance
- Ticket
- Reservation
- Rehearsal
- Budget
- Production Actual
- Document
- Announcement
- Survey

などはProductionに関連付けて管理する。

---

# Production Scope

Productionに関連するBusiness Dataは、
Production Scopeの中で管理する。

主なDomain：

- Participant
- ProductionDelegate
- Performance
- Ticket
- Reservation
- CheckIn
- Rehearsal
- Timetable
- Budget
- Production Actual
- Document
- Announcement
- Survey

Production関連Domainは、
Productionを通じてOrganization Scopeに属する。

---

# Organization Public Information

Organizationの公開情報は、
一般利用者が閲覧できるPublic Informationとして管理する。

基本的な公開情報は、

- 団体名
- 沿革
- 代表
- メンバー
- 過去公演情報
- SNS情報

などとする。

メンバー情報や過去公演情報は、
Organization内部のFactから生成・参照する。

内部管理情報をPublic Informationとして公開してはならない。

---

# Public Profile

Organizationの公開ページは、
OrganizationのFactおよび関連Domainから生成されるPublic Artifactとして扱う。

Organization Public Profileでは、
団体の公開情報を表示する。

公開ページに表示する情報は、
公開対象として定義された情報に限定する。

---

# Internal Information

Organization内部には、
一般公開しない情報が存在する。

例：

- メンバー権限
- 管理権限
- 会計情報
- 予算情報
- 内部ファイル
- 内部お知らせ
- 外部サービス認証情報
- その他内部管理情報

これらはPublic Profileから公開してはならない。

---

# Organization Information

Organizationは、
団体そのものに関する基本情報を管理する。

例：

- Organization Name
- Organization Type
- Description
- History
- Representative
- Activity Area
- Website
- Logo
- Public Settings

具体的な公開範囲はPublic Profileのルールに従う。

---

# History

Organizationの活動履歴はHistory Domainによって管理する。

Organizationに関連するHistoryには、

- 公演履歴
- 活動履歴
- 制作履歴
- その他団体活動履歴

などが含まれる。

HistoryはOrganization自身が直接保持するのではなく、
History Domainによって関連付ける。

Organizationに関連するHistoryは、
Project、Production、ParticipantなどのFactから生成できる。

---

# Accounting

Organizationは団体全体の会計を管理する。

主なDomain：

- Accounting Period
- Account
- Journal Entry
- Journal Entry Line

AccountingはOrganization単位で管理する。

Production単位のBudgetおよびProduction Actualとは、
異なる目的を持つ。

AccountはAccounting Domainにおける
勘定科目を表す。

AccountはAuthentication Identityではない。

Authentication IdentityはUserAccountで管理する。

---

# Equipment

Organizationは備品を管理する。

EquipmentはOrganizationに所属する。

備品管理は資産価値を管理することを目的としない。

主な目的は、

- 何があるか
- どこにあるか
- 誰が持っているか
- 使用可能か
- 不明か
- 廃棄されたか

を明らかにすることである。

Equipmentの取得価格、
資産価値、
減価償却は管理しない。

---

# Regulation

Organizationは規約を管理できる。

RegulationはOrganizationに所属する。

規約を変更する場合は、
既存Versionを上書きせず、
新しいRegulation Versionを作成する。

Organization
    ↓
Regulation
    ├── Version 1
    ├── Version 2
    └── Version 3

---

# Document

OrganizationおよびそのProject / Productionに関連する
Documentを管理できる。

実ファイルはGoogle Driveなどの外部ストレージと連携する。

StageArtでは、

- ファイル情報
- 関連するProject / Production
- 共有対象
- 外部ファイル参照情報

などを管理する。

---

# Announcement

OrganizationまたはProductionの関係者へ、
内部のお知らせを送信できる。

適切なRole / Permissionを持つPersonが、
Announcementを作成できる。

対象者は、

- CAST
- STAFF
- 制作
- その他関係者

などから指定できる。

ここでのCAST / STAFFは、
ProductionにおけるParticipant Typeを意味する。

Announcementの作成権限は、
Organization ScopeまたはProduction Scopeの
Role / Permissionによって決定する。

---

# External Connection

Organizationは外部サービスとの接続を、
ExternalConnectionとして管理する。

ExternalConnectionはOrganizationの子Entityである。

基本構造：

Organization
    ↓
ExternalConnection
    ├── Service
    ├── Account Identifier
    └── Credential

ExternalConnectionはSNS専用のDomainではない。

例えば、

- X
- Instagram
- Facebook
- YouTube
- TikTok
- LINE
- Google
- Google Drive
- Google Calendar

などを外部Serviceとして扱うことができる。

---

# External Service

外部サービスの種類はServiceによって管理する。

Serviceは、

- X
- Instagram
- Facebook
- Google
- Google Drive
- Google Calendar

などの外部サービスを識別する。

特定サービス固有のBusiness Logicは、
Organization Domainへ持ち込まない。

---

# External Account

ExternalConnectionは、
外部サービス上のAccountを識別するための情報を保持する。

基本構造：

ExternalConnection
    ├── Service
    ├── Account Identifier
    └── Credential

Account Identifierは、

- 外部サービスのAccount ID
- ユーザー名
- Page ID
- その他外部サービス上の識別子

などを表す。

StageArt内部のAccountとは別の概念である。

---

# Credential

Credentialは、
ExternalConnectionに属する外部サービスの認証情報を表す。

Credentialには必要に応じて、

- OAuth Token
- Access Token
- Refresh Token
- Secret

などを保持する。

認証情報は平文で保存しない。

暗号化、
Secret管理、
Token更新などの具体的な実装はInfrastructure Layerで管理する。

Domain Modelは、
特定の認証方式へ直接依存しない。

---

# External Connection Scope

ExternalConnectionはOrganizationに所属する。

異なるOrganizationのExternalConnectionを共有してはならない。

例：

Organization A
    └── ExternalConnection
            └── Instagram A

Organization B
    └── ExternalConnection
            └── Instagram B

Organization Aの認証情報を、
Organization Bから利用することはできない。

---

# External Connection Lifecycle

ExternalConnectionは以下の状態を持つ。

- CONNECTED
- DISCONNECTED
- ERROR

CONNECTED：

外部サービスとの接続が有効。

DISCONNECTED：

接続情報は保持するが、
外部サービスへの操作は実行しない。

ERROR：

認証期限切れなどにより、
再認証等が必要な状態。

具体的な状態遷移はExternalConnection Domainで定義する。

---

# External Service Operations

外部サービスへの実際のAPI呼び出しは、
Infrastructure Layerが担当する。

Domain Layerは、

- X
- Instagram
- Google
- Google Drive
- Google Calendar

などの特定サービスへ直接依存しない。

ExternalConnectionは、
外部サービスを操作するために必要な接続情報を提供する。

---

# SNS

SNSはExternalConnectionの特別な子Entityとして扱わない。

SNSも外部サービスの一種としてServiceで管理する。

OrganizationのPublic ProfileにSNS情報を表示する場合は、
公開対象となるアカウント情報のみを参照する。

Credentialや内部接続情報を公開してはならない。

StageArtはSNS投稿内容そのものをDomainの正本として管理しない。

SNSへの投稿機能を提供する場合も、
投稿本文などをStageArt内のSocial Post Domainとして永続管理することを前提としない。

---

# Google Drive

Google Driveは、
Documentの外部保存先として利用する。

StageArtはGoogle Drive上の実ファイルそのものを正本として管理しない。

StageArtでは、

- File Identifier
- File Name
- File Type
- External URL / Reference
- Project / Productionとの関連
- 共有対象

などの情報を管理する。

---

# Google Calendar

Google Calendarは、
Rehearsalを外部Calendarへ連携するために利用する。

CONFIRMEDとなったRehearsalを、
Google Calendarへ登録できる。

Google Calendarへの登録対象は、
RehearsalAttendanceの参加予定者だけに限定しない。

Google Calendar Eventは、
StageArt上のRehearsalとは別のExternal Artifactとして扱う。

StageArt上のRehearsalを正本とする。

---

# External Connection Authorization

ExternalConnectionの管理は、
Organizationを管理する権限を持つPersonが実行できる。

基本的には、

- Organization Owner
- 適切なOrganization Role
- 適切なPermission

を持つPersonが対象となる。

ProductionDelegateによるProduction Scopeの権限は、
Organization全体のExternalConnection操作権限とは別に扱う。

---

# Automatically Generated

Organization作成時、
StageArtは必要な基本情報を自動生成する。

例：

- Owner Membership
- Default Role
- Default Settings

Owner Membershipには、
Organization管理に必要なRoleを適用する。

ProjectやProductionを作成する場合は、
それぞれのDomainのBusiness Ruleに従って関連Domainを生成する。

ExternalConnectionは、
Organization作成時には自動生成しない。

外部サービスとの接続は、
Organization管理権限を持つPersonが必要に応じて設定する。

---

# Lifecycle

Organizationは以下の状態を持つ。

- ACTIVE
- ARCHIVED
- DELETED

DELETEDは論理削除とする。

過去のProject、
Production、
Accountingなどの履歴との整合性を維持する。

OrganizationがArchivedまたはDeletedになった場合、
新規Business Activityの作成を制限する。

既存データの参照可否は、
LifecycleおよびAuthorizationのルールに従う。

---

# Audit Information

Organizationの重要な管理操作について、
監査情報を記録できるようにする。

基本的な監査情報として、

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

を利用する。

Credentialなどの認証情報そのものを
監査情報として記録しない。

---

# Domain Events

Organizationに関する主なDomain Event：

- OrganizationCreated
- OrganizationUpdated
- OrganizationArchived
- OrganizationDeleted
- MembershipCreated
- MembershipUpdated
- MembershipRemoved

ExternalConnectionに関するEventは、
ExternalConnection Domainで定義する。

---

# Design Decisions

OrganizationはStageArtにおけるTenantである。

Organizationは団体を表すBusiness Domainである。

Organizationは「劇団」に限定しない。

PersonとOrganizationは別のIdentityとして管理する。

Personとの所属関係はMembershipで管理する。

Organization内の権限はMembershipに関連するRoleで管理する。

Role DefinitionはRole Domainで管理する。

RoleはPermission Setを定義する。

Organization ScopeのRole適用はMembershipによって表現する。

Organization Delegateという独立Domainは作成しない。

DelegateRoleという別のRole体系は使用しない。

RoleAssignmentという独立Domainは作成しない。

Production ScopeのRole適用はProductionDelegateによって表現する。

Organization OwnerもMembership / Roleによって表現する。

Organization自身にOwnerIdを直接保持しない。

Organizationの活動・制作はProjectによって管理する。

ProductionはProjectに所属する。

基本構造は、

Organization
    ↓
Project
    ↓
Production

である。

Production関連Domainは、
Productionを通じてOrganization Scopeに属する。

OrganizationはMemberやProductionを直接保持するのではなく、
それぞれのDomainを通じて関連付ける。

Historyは独立Domainとして管理する。

AccountingはOrganization単位で管理する。

BudgetおよびProduction ActualはProduction単位で管理する。

EquipmentはOrganizationに所属するが、
資産管理Domainではない。

ExternalConnectionはOrganizationの子Entityである。

ExternalConnectionはSNS専用ではない。

SNS、Google Drive、Google CalendarなどはServiceとして扱う。

Credentialは平文保存しない。

外部サービスへのAPIアクセスはInfrastructure Layerが担当する。

SNS投稿内容はStageArtの正本として管理しない。

Public InformationとInternal Informationを明確に分離する。

内部情報をPublic Profileへ公開してはならない。

---

# Design Principles

- OrganizationはTenantである。
- Organizationは団体を表すBusiness Domainである。
- Organizationは劇団に限定しない。
- PersonとOrganizationは別軸として管理する。
- Personとの所属関係はMembershipで管理する。
- Organization内の権限はRoleで管理する。
- RoleはPermission Setを定義する。
- Organization ScopeのRole適用はMembershipで管理する。
- Organization OwnerはMembership / Roleによって表現する。
- Organization自身にOwnerIdを直接保持しない。
- Organization Delegateという独立Domainを作成しない。
- DelegateRoleという別のRole体系を使用しない。
- RoleAssignmentという独立Domainを作成しない。
- Production ScopeのRole適用はProductionDelegateで管理する。
- Role DefinitionはOrganization ScopeとProduction Scopeで共通利用する。
- ParticipantとMembershipを分離する。
- Participant TypeとRoleを分離する。
- CAST / STAFFはParticipant TypeでありRoleではない。
- Organizationの活動・制作はProjectで管理する。
- Projectの下にProductionを持つ。
- Production関連DomainはProductionを通じてOrganization Scopeに属する。
- Historyは独立Domainとして管理する。
- AccountingはOrganization単位で管理する。
- AccountはAccounting Domainの勘定科目である。
- AccountとUserAccountを分離する。
- BudgetおよびProduction ActualはProduction単位で管理する。
- Equipmentは資産管理を目的としない。
- ExternalConnectionはOrganizationの子Entityである。
- ExternalConnectionはSNSに限定しない。
- 外部サービスの種類はServiceで管理する。
- 外部サービスの認証情報はCredentialで管理する。
- Credentialは平文保存しない。
- 外部サービス固有のAPI処理はInfrastructure Layerで実装する。
- SNS投稿内容はStageArtの正本として管理しない。
- Google DriveはDocumentの外部保存先として利用する。
- Google CalendarはRehearsalの外部連携先として利用する。
- Blueprintを唯一の設計基準とする。