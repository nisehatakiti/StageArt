# StageArt Blueprint

# Domain Model : Organization

Version : 3.3

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

一つのMembershipは、
基本的に一つのRoleを参照する。

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

一つのMembershipへ複数Roleを
直接付与する構造は基本設計としない。

複数のPermissionが必要な場合は、
RoleのPermission Setによって表現する。

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
特定Productionの管理権限をPersonへ適用する関係を表す。

ProductionDelegateは、
Organization全体のRoleを変更しない。

ProductionDelegateによって、
Organization全体の権限を与えずに、
特定Productionのみを管理できる。

ProductionDelegateの詳細な構造は、
Production Domainで定義する。

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

Organization ScopeのRoleは、
Production Scopeへ自動的に継承されない。

Production ScopeのRoleも、
Organization Scopeへ自動的に継承されない。

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

Production単位の権限についても、
ProductionDelegateから通常のRoleを参照する。

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

Projectの詳細はProject Domainで定義する。

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
- CheckIn
- Rehearsal
- Timetable
- Budget
- Production Actual
- Document
- Announcement
- Survey
- ProductionDelegate

などはProductionに関連付けて管理する。

これらのProduction関連Domainを、
Organizationの直接の子Domainとして重複管理しない。

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

例えば、

Organization
    ↓
Project
    ↓
Production
    ↓
Rehearsal

という関係において、
RehearsalのOrganization Scopeは
Productionから解決する。

---

# Production Primary Manager

Productionには、
PrimaryManagerが存在する。

PrimaryManagerは、
Productionに関する全管理権限を持つ。

PrimaryManagerは、
Organization Ownerとは異なる。

Organization Owner：

Organization Scopeの管理者

PrimaryManager：

Production Scopeの管理者

ProductionDelegateとは異なり、
PrimaryManagerはRoleによる限定権限ではなく、
Production Scopeの全管理権限を持つ。

一人のPersonは、
複数のProductionのPrimaryManagerになることができる。

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
OrganizationのFactおよび関連Domainから生成される
Public Artifactとして扱う。

Organization Public Profileでは、
団体の公開情報を表示する。

公開ページに表示する情報は、
公開対象として定義された情報に限定する。

Membershipの内部StatusやPermission、
Accounting情報、
Credentialなどの内部情報を
Public Profileへ公開してはならない。

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

AccountとUserAccountは、
同じ「Account」という意味で混在させない。

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

Documentの実ファイルそのものは、
StageArtの正本として保持しない。

---

# Announcement

OrganizationまたはProductionの関係者へ、
内部のお知らせを送信できる。

適切なRole / Permissionを持つPersonが、
Announcementを作成できる。

Organization ScopeのAnnouncementと、
Production ScopeのAnnouncementを区別する。

Productionに関するAnnouncementは、
Productionを関連先として管理する。

---

# External Connection

Organizationは、
外部サービスとのConnectionを管理できる。

例：

- Google
- Google Drive
- Google Calendar
- LINE
- SNS
- その他外部サービス

External Connectionは、
Organization単位で管理する。

External Connectionに必要なCredentialは、
平文で保存しない。

Secret情報は、
Secret Management / Infrastructure Layerで安全に管理する。

External Connectionの詳細は、
External Connection Domainで定義する。

---

# SNS

Organizationは、
SNSなどのExternal Serviceを管理できる。

SNSはOrganizationに関連するExternal Connectionとして扱う。

基本構造：

Organization
    ↓
External Connection
    ↓
External Service

SNSの公開情報と認証情報を分離する。

公開情報として、

- Site
- Account Identifier
- Public URL

などを管理できる。

Credentialは公開情報として扱わない。

Credentialは暗号化されたSecret Storageなどで管理する。

StageArtからSNSへの投稿など、
外部サービスへのAPI操作はInfrastructure Layerが担当する。

---

# Authorization

Organizationに対するAuthorizationは、
Membershipを起点として判定する。

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

MembershipがACTIVEであることを基本条件とし、
Roleに含まれるPermissionによって操作可否を判定する。

一つのMembershipは、
基本的に一つのRoleを参照する。

Organization ScopeのRoleは、
Production Scopeへ自動継承されない。

Production Scopeの権限は、
ProductionDelegateまたはPrimaryManagerによって判定する。

Authorizationの詳細はAuthorization Domainで定義する。

---

# Organization Scope Boundary

Organization Scopeでは、
Organizationそのものと、
Organizationに属するInternal Domainを管理する。

Organization Scopeの代表的なDomain：

- Membership
- Project
- Accounting
- Equipment
- Document
- External Connection
- Regulation
- Announcement

Production関連Domainについては、
Productionを経由してOrganization Scopeに属する。

Production Scopeの権限を、
Organization Scopeの権限と混同しない。

---

# Lifecycle

Organizationは、
団体そのもののLifecycleを管理する。

基本的な状態：

- ACTIVE
- INACTIVE
- ARCHIVED

Organizationを利用停止またはArchiveしても、
過去のProject、
Production、
Accounting、
HistoryなどのFactを削除してはならない。

OrganizationのLifecycleと、
MembershipやProductionのLifecycleは、
それぞれ独立して管理する。

---

# Organization and Membership Lifecycle

OrganizationがACTIVEである場合、
Membershipを通常どおり利用できる。

OrganizationがINACTIVEまたはARCHIVEDになった場合、
新規Membership操作やBusiness Activityを制限できる。

既存Membershipの履歴は保持する。

OrganizationのStatus変更によって、
Membershipそのものを物理削除しない。

---

# Organization and Project Lifecycle

OrganizationはProjectを保持する。

ProjectのLifecycleは、
OrganizationのLifecycleとは分離する。

OrganizationがArchiveされた場合でも、
過去Projectは履歴として保持する。

Projectの詳細なLifecycleはProject Domainで定義する。

---

# Organization and Production Lifecycle

ProductionはProjectに所属する。

ProductionのLifecycleは、
OrganizationおよびProjectとは独立して管理する。

OrganizationのStatus変更によって、
ProductionのStatusを自動的に同一化しない。

Productionの詳細なLifecycleはProduction Domainで定義する。

---

# Audit Information

Organizationの重要な管理操作について、
監査情報を記録できるようにする。

基本的な監査情報：

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

OrganizationのLifecycle変更、
Owner変更、
重要な設定変更などについても、
必要に応じて監査情報を保持する。

---

# Domain Events

Organizationに関する主なDomain Event：

- OrganizationCreated
- OrganizationUpdated
- OrganizationActivated
- OrganizationDeactivated
- OrganizationArchived

Organizationの関連Domainでは、
各DomainのEventを別途定義する。

例えば：

Membership：

- MembershipRequested
- MembershipInvited
- MembershipApproved
- MembershipRejected
- MembershipSuspended
- MembershipReactivated
- MembershipLeft
- MembershipRoleChanged

Project：

- ProjectCreated
- ProjectUpdated
- ProjectClosed
- ProjectArchived

Production：

- ProductionCreated
- ProductionUpdated
- ProductionCompleted
- ProductionCancelled
- ProductionArchived

Organization Domain自身が、
これらの関連DomainのEventを直接管理するわけではない。

---

# Business Rules

- Organizationは舞台芸術活動を行う団体を表す。
- OrganizationはStageArtにおけるTenantである。
- Organizationは劇団に限定しない。
- OrganizationIdは変更しない。
- Organization Nameは変更できる。
- Organizationに属するBusiness DataはOrganization Scopeで管理する。
- PersonとOrganizationの所属関係はMembershipで管理する。
- OrganizationはPersonを直接保持しない。
- Personは複数Organizationへ所属できる。
- 一つのMembershipは基本的に一つのRoleを参照する。
- RoleはPersonの属性ではない。
- RoleはMembershipを通じてOrganization Scopeへ適用する。
- 一つのMembershipへ複数Roleを直接付与する構造を基本設計としない。
- Role DefinitionはRole Domainで共通管理する。
- RoleAssignmentという独立Domainを作成しない。
- DelegateRoleという独立Role体系を作成しない。
- Production単位のRole適用はProductionDelegateで管理する。
- ProductionDelegateはOrganization ScopeのMembershipとは別の関係である。
- ProductionDelegateのRoleはOrganization Scopeへ自動継承されない。
- Organization ScopeのRoleはProduction Scopeへ自動継承されない。
- Organization Ownerは独立したRole体系として作成しない。
- Organization OwnerはMembershipとRoleによって表現する。
- Production PrimaryManagerはOrganization Ownerとは異なる。
- ProjectはOrganizationに所属する。
- ProductionはProjectに所属する。
- Production関連DomainはProductionを中心に管理する。
- Production関連DomainをOrganizationの直接の子Domainとして重複管理しない。
- Organization AccountingはOrganization単位で管理する。
- AccountはAccounting Domainの勘定科目である。
- AccountとUserAccountを混同しない。
- Authentication IdentityはUserAccountで管理する。
- ProjectのBudgetとProductionのBudgetを混同しない。
- Production単位のBudget / Production ActualはProduction Scopeで管理する。
- Organization Public Profileには公開対象情報のみ表示する。
- Membershipの内部権限情報をPublic Profileへ公開しない。
- Accounting情報をPublic Profileへ公開しない。
- External ConnectionのCredentialをPublic Profileへ公開しない。
- External ConnectionのCredentialを平文で保存しない。
- OrganizationのLifecycleとMembershipのLifecycleを分離する。
- OrganizationのLifecycleとProjectのLifecycleを分離する。
- OrganizationのLifecycleとProductionのLifecycleを分離する。
- Organizationを物理削除することで過去のBusiness Factを破壊しない。

---

# Design Decisions

OrganizationはStageArtにおけるTenantである。

Organizationは、
Personの集合そのものではない。

PersonとOrganizationの関係は、
Membershipという独立した所属関係によって表現する。

基本構造：

Person
    ↓
Membership
    ↓
Organization

Organization Scopeの権限は、

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

によって判定する。

一つのMembershipは、
基本的に一つのRoleを参照する。

RoleAssignmentという独立Domainは作成しない。

Production単位の権限は、

Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

によって判定する。

Organization OwnerとProduction PrimaryManagerは、
異なるScopeの管理者である。

OrganizationはProjectを保持し、

Organization
    ↓
Project
    ↓
Production

という階層を形成する。

Productionに関連する、

- Participant
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

などはProductionを中心に管理する。

稽古については、
RehearsalをProductionの一つのEntityとして管理する。

稽古予定と確定稽古を別Entityとして作成しない。

RehearsalのLifecycleはStatusで管理し、
RehearsalAttendanceはLifecycle全体を通じて保持する。

Organization Accountingは、
ProjectおよびProductionの予算・実績とは分離する。

AccountingのAccountとAuthenticationのUserAccountは、
完全に別の概念として扱う。

Organization Public Profileは、
Organizationおよび関連Domainの公開対象情報から生成する。

内部管理情報、
権限情報、
会計情報、
Credentialなどは公開しない。

---

# Future

将来的にOrganization単位で、

- 複数Project
- 複数Production
- 複数Membership
- Organization独自Role
- External Connection
- SNS連携
- 会計
- 備品管理
- 規約管理
- ドキュメント管理
- 内部Announcement
- Organization Public Profile

などを拡張できる構造とする。

ただし、
Production固有のDomainをOrganizationへ移動させない。

また、
Production ScopeのRoleをOrganization ScopeのRoleへ統合しない。

Organization ScopeとProduction Scopeを明確に分離する。

---

# Design Principles

- OrganizationはStageArtにおけるTenantである。
- Organizationは舞台芸術活動を行う団体を表す。
- Organizationは劇団に限定しない。
- OrganizationはPersonを直接保持しない。
- PersonとOrganizationの関係はMembershipで管理する。
- Personは複数Organizationへ所属できる。
- 一つのMembershipは基本的に一つのRoleを参照する。
- RoleはPersonの属性ではない。
- RoleはMembershipを通じて適用する。
- Role Definitionは共通管理する。
- RoleAssignmentという独立Domainを作成しない。
- DelegateRoleという別Role体系を作成しない。
- Organization ScopeとProduction Scopeを分離する。
- Production単位の権限はProductionDelegateで管理する。
- ProductionDelegateは通常のRole Definitionを利用する。
- Organization OwnerはMembershipとRoleによって表現する。
- Production PrimaryManagerはOrganization Ownerとは別概念である。
- OrganizationはProjectを保持する。
- ProjectはProductionを保持する。
- Production関連DomainはProductionを中心に管理する。
- Production関連DomainをOrganization直下で重複管理しない。
- Organization AccountingとProduction Budget / Actualを分離する。
- Accounting AccountとAuthentication UserAccountを明確に分離する。
- Organization Public Profileには公開対象情報のみ表示する。
- 内部権限情報を公開しない。
- Credentialを公開しない。
- External ConnectionのSecretを平文保存しない。
- Organization LifecycleとMembership Lifecycleを分離する。
- Organization LifecycleとProject Lifecycleを分離する。
- Organization LifecycleとProduction Lifecycleを分離する。
- RehearsalはProductionに所属する。
- Rehearsalは一つのEntityとしてLifecycleを管理する。
- RehearsalAttendanceはRehearsalのLifecycleを通じて保持する。
- Blueprintを唯一の設計基準とする。