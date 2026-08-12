# StageArt Blueprint

# Domain Model : UserAccount

Version : 1.0

---

# Purpose

UserAccountは、
StageArtを利用するPersonの認証Identityを表すDomainである。

UserAccountは、
舞台芸術活動上のPersonそのものを表すものではない。

PersonはBusiness Identityであり、
UserAccountはStageArtへのアクセスを行うための
Authentication Identityである。

基本構造：

UserAccount
    ↓
Person

---

# Concept

StageArtでは、

- UserAccount
- Person
- Organization

を別の概念として管理する。

UserAccount：

StageArtへログイン・アクセスするIdentity。

Person：

StageArt上の個人を表すBusiness Identity。

Organization：

舞台芸術活動を行う団体を表すBusiness Domain。

UserAccountの存在によって、
Personの属性やOrganizationへの所属が
自動的に決定されることはない。

---

# Identity

UserAccountはUserAccountIdによって
一意に識別する。

UserAccountIdは変更できない。

UserAccountのLogin Identifierは、
UserAccountIdとは別の概念として扱う。

Login Identifierとして、

- Email
- External Provider Identifier
- その他認証Provider固有Identifier

などを利用できる。

具体的な認証方式は、
Authentication / Infrastructure Layerで管理する。

---

# Person Relationship

UserAccountは、
Personと関連付ける。

基本構造：

UserAccount
    ↓
Person

UserAccountは、
Personの認証Identityを表す。

Personは、
UserAccountそのものではない。

Personの氏名、
プロフィール、
活動履歴、
Organization Membershipなどは、
Person Domainで管理する。

---

# Person Independence

Personは、
必ずしもUserAccountを持つ必要はない。

例えば、

- 招待された外部スタッフ
- 過去の出演者
- 観客
- 取引先関係者
- その他StageArt上で記録する人物

など、
認証を必要としないPersonを
表現できる。

その場合、

Person

のみを存在させることができる。

---

# UserAccount Creation

UserAccountは、
StageArtへのアクセスが必要になった場合に
作成する。

基本的な流れ：

Person
    ↓
UserAccount
    ↓
Authentication

UserAccount作成時に、
既存Personへ関連付けることができる。

既存Personが存在しない場合の
Person作成ルールは、
Authentication / Application Layerで定義する。

---

# Multiple UserAccounts

基本設計では、
一人のPersonに対して
一つのActive UserAccountを利用する。

基本構造：

Person
    ↓
UserAccount

過去の認証Identityを保持する必要がある場合は、
UserAccountのLifecycleによって管理する。

複数のActive UserAccountを
同一Personへ関連付ける必要が生じた場合は、
Authentication設計で明示的に許可する。

---

# Authentication Provider

UserAccountは、
外部Authentication Providerと連携できる。

例：

- Google
- Apple
- Microsoft
- Email / Password
- その他Authentication Provider

外部Provider固有の認証処理は、
Infrastructure Layerが担当する。

Domain Layerは、
特定Authentication ProviderのAPIへ
直接依存しない。

---

# External Identity

外部Authentication Provider上のIdentityは、
UserAccountに関連付ける。

基本構造：

UserAccount
    ↓
External Identity
    ↓
Provider

External Identityは、

- Provider
- Provider User Identifier

などによって識別する。

Provider固有のAPI仕様は、
Infrastructure Layerで管理する。

---

# Email

EmailをLogin Identifierとして
利用できる。

ただし、

PersonのContact InformationとしてのEmail

と、

UserAccountのAuthentication IdentifierとしてのEmail

は、
同一概念として扱わない。

認証に利用するEmailの管理ルールは、
Authentication Domainで定義する。

---

# Credential

UserAccountの認証に必要なCredentialは、
安全に管理する。

Passwordを利用する場合、
平文Passwordを保存してはならない。

OAuth Tokenなどの認証情報を利用する場合も、
安全なCredential Storageを利用する。

具体的な暗号化、
Hashing、
Secret Management、
Token Rotationなどは、
Infrastructure Layerで管理する。

UserAccount Domainは、
具体的なCredential Storage方式へ
直接依存しない。

---

# Session

UserAccountへの認証後、
SessionまたはTokenによって
StageArtへのアクセスを許可する。

Session / Tokenの具体的な管理は、
Authentication / Infrastructure Layerで行う。

Session自体をPersonやOrganizationの
Business Dataとして扱わない。

---

# Authorization Relationship

UserAccountは、
Business Authorizationを直接保持しない。

AuthorizationはPersonを起点として評価する。

基本構造：

UserAccount
    ↓
Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

Production Scopeでは、

UserAccount
    ↓
Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

となる。

UserAccount自身に
Organization RoleやProduction Roleを
直接付与しない。

---

# Organization Relationship

UserAccountは、
Organizationに直接所属しない。

Organizationへの所属は、

Person
    ↓
Membership
    ↓
Organization

によって表現する。

UserAccountは、
Personを通じてOrganization Scopeへ
アクセスする。

---

# Production Relationship

UserAccountは、
Productionに直接所属しない。

Productionへの参加は、

Person
    ↓
Participant
    ↓
Production

によって表現する。

Production Scopeの管理権限は、

Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role

によって表現する。

---

# Profile Relationship

ProfileはPersonに属する。

基本構造：

UserAccount
    ↓
Person
    ↓
Profile

UserAccountがProfileを直接保持することはない。

UserAccountの認証情報と、
Personの公開プロフィール情報を
分離する。

---

# Historical Activity Relationship

HistoricalActivityはPersonに関連する。

基本構造：

UserAccount
    ↓
Person
    ↓
HistoricalActivity

UserAccountがHistoricalActivityを
直接保持することはない。

---

# Status

UserAccountは状態を持つ。

基本的な状態：

- ACTIVE
- SUSPENDED
- DISABLED

---

# ACTIVE

ACTIVEは、
UserAccountが通常利用可能な状態を表す。

Authenticationを通じて、
StageArtへのアクセスを許可できる。

---

# SUSPENDED

SUSPENDEDは、
一時的に利用を停止している状態を表す。

Suspended状態では、
新しいAuthenticationを
許可しない。

Personそのものを削除するわけではない。

---

# DISABLED

DISABLEDは、
UserAccountを恒久的に利用停止した状態を表す。

DISABLEDになったUserAccountを
再利用するかどうかは、
Authentication Policyで定義する。

Personおよび過去のBusiness Dataは
削除しない。

---

# UserAccount Deletion

UserAccountを削除しても、
Personを削除しない。

例えば、

UserAccount
    ↓
Person
    ↓
Membership
    ↓
Organization

という構造において、
UserAccountを削除・無効化しても、

Person
Membership
Organization

などのBusiness Dataは維持する。

UserAccountのLifecycleと、
PersonのLifecycleを分離する。

---

# Audit Information

UserAccountの重要な管理操作について、
監査情報を保持できる。

基本的な情報：

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

認証試行などのSecurity Auditについては、
Authentication / Security Layerで管理する。

PasswordやTokenなどのCredentialそのものを
Audit Informationとして保存しない。

---

# Security

UserAccountは、
Authentication Identityを扱うため、
Security上の保護対象となる。

基本原則：

- Passwordを平文保存しない。
- Access Tokenを平文保存しない。
- Refresh Tokenを平文保存しない。
- SecretをDomain Eventへ含めない。
- Credentialを通常のBusiness Dataとして公開しない。
- UserAccount情報をPublic Profileへ公開しない。

具体的なSecurity実装は、
Infrastructure / Security Layerで管理する。

---

# Public Information

UserAccountは、
Public Informationを管理するDomainではない。

Public Profileに表示する情報は、
Person / Profileなどの公開対象情報から取得する。

以下をPublic Profileへ公開してはならない。

- UserAccountId
- Login Identifier
- External Provider Identifier
- Credential
- Session Information
- Access Token
- Refresh Token
- Security Information

---

# Domain Boundary

UserAccountは、
Authentication Identityを管理する。

Personは、
Business Identityを管理する。

Membershipは、
Organizationへの所属を管理する。

Roleは、
Permission Setを管理する。

ProductionDelegateは、
Production ScopeのRole Assignmentを管理する。

これらを統合しない。

---

# Domain Events

UserAccountに関する主なDomain Event：

- UserAccountCreated
- UserAccountActivated
- UserAccountSuspended
- UserAccountDisabled
- UserAccountPersonLinked
- UserAccountPersonUnlinked

Authentication成功・失敗などの
Security Eventについては、
Authentication / Security Domainで管理する。

---

# Business Rules

- UserAccountはStageArtへのAuthentication Identityを表す。
- UserAccountはPersonそのものではない。
- UserAccountとPersonを分離する。
- UserAccountはPersonに関連付ける。
- PersonはUserAccountを持たなくてもよい。
- UserAccountはOrganizationに直接所属しない。
- Organizationへの所属はMembershipで管理する。
- UserAccountはProductionに直接所属しない。
- Productionへの参加はParticipantで管理する。
- Production Scopeの権限はProductionDelegateで管理する。
- UserAccount自身にRoleを直接付与しない。
- AuthorizationはPersonを起点として評価する。
- ProfileはPersonに所属する。
- HistoricalActivityはPersonに関連する。
- UserAccountのLifecycleとPersonのLifecycleを分離する。
- UserAccountを無効化してもPersonのBusiness Dataを削除しない。
- Passwordを平文保存しない。
- Access Tokenを平文保存しない。
- Refresh Tokenを平文保存しない。
- CredentialをPublic Informationとして公開しない。
- UserAccountのSecurity InformationをPublic Profileへ公開しない。
- Authentication Provider固有のBusiness LogicをUserAccount Domainへ持ち込まない。
- 外部Authentication ProviderとのAPI通信はInfrastructure Layerが担当する。
- UserAccountの認証情報は安全なStorageで管理する。
- UserAccountのSecurity AuditとBusiness Auditを必要に応じて分離する。
- UserAccount IdをPerson Idとして利用しない。
- UserAccount IdをOrganization ScopeのIdentityとして利用しない。

---

# Design Decisions

StageArtでは、
Authentication IdentityとBusiness Identityを分離する。

Authentication IdentityはUserAccountで表現する。

Business IdentityはPersonで表現する。

基本構造：

UserAccount
    ↓
Person

Personは、
Organizationへの所属、
Productionへの参加、
Production Scopeの管理権限などの
Business Relationshipを持つ。

UserAccountは、
これらのBusiness Relationshipを
直接保持しない。

Organization Scope：

UserAccount
    ↓
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

UserAccount
    ↓
Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

これにより、

- 認証Identity
- 個人Identity
- Organization Membership
- Organization Role
- Production Role

を明確に分離する。

また、
会計DomainのAccountとは完全に別の概念として扱う。

会計Account：

Organization
    ↓
Account
    ↓
Journal Entry Line

認証Account：

UserAccount
    ↓
Person

という構造を採用する。

同じ「Account」という名称によるDomain上の意味の衝突を避けるため、
Authentication IdentityにはUserAccountという名称を使用する。

---

# Design Principles

- UserAccountはAuthentication Identityである。
- PersonはBusiness Identityである。
- UserAccountとPersonを分離する。
- UserAccountはPersonに関連付ける。
- PersonはUserAccountを持たなくてもよい。
- UserAccountはOrganizationに直接所属しない。
- Organizationへの所属はMembershipで管理する。
- UserAccountはProductionに直接所属しない。
- Productionへの参加はParticipantで管理する。
- Production Scopeの権限はProductionDelegateで管理する。
- RoleはPermission Setを定義する。
- UserAccount自身にRoleを直接付与しない。
- AuthorizationはPersonを起点として評価する。
- ProfileはPersonに所属する。
- HistoricalActivityはPersonに関連する。
- UserAccountのLifecycleとPersonのLifecycleを分離する。
- UserAccountを無効化してもPersonのBusiness Dataを削除しない。
- Authentication Provider固有の処理はInfrastructure Layerで実装する。
- Credentialは安全に管理する。
- Credentialを平文保存しない。
- Security InformationをPublic Profileへ公開しない。
- 会計DomainのAccountとUserAccountを明確に分離する。
- 会計AccountはOrganization Scopeで管理する。
- UserAccountはAuthentication Scopeで管理する。
- Blueprintを唯一の設計基準とする。
