# StageArt Blueprint

# Domain Model : Role

Version : 2.0

---

# Purpose

Roleは、
PersonがOrganization Contextにおいて持つ
管理・運営上の権限を表すDomainである。

RoleはPerson自身の属性ではない。

RoleはMembershipを通じて、
特定のOrganizationにおけるPersonへ適用される。

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

RoleはProductionへの参加区分とは異なる。

OrganizationにおけるRoleは、

「そのOrganizationで何を管理できるか」

を表す。

ProductionにおけるParticipant Typeは、

「そのProductionにどう関わっているか」

を表す。

この2つは明確に分離する。

---

# Concept

RoleはOrganization Contextに属する。

Person
  ↓
Membership
  ↓
Organization
  ↓
Role

同じPersonでも、
Organizationごとに異なるRoleを持つことができる。

例：

Person A

  Organization A
    Role = 管理者

  Organization B
    Role = 稽古管理者

Person自身にRoleを直接持たせない。

---

# Role Definition

Roleは、
Organization内で利用可能なPermissionのまとまりとして定義する。

基本的な構造：

Role
  ↓
Permission Set

Role Definitionそのものは、
Personごとに複製しない。

PersonへのRoleの付与状態は、
Membershipを通じて管理する。

---

# Role Types

StageArtの基本Roleは、
以下を提供する。

- 管理者
- 稽古管理者
- 会計管理者

初期実装では、
Roleを必要以上に細分化しない。

---

# Administrator

管理者。

Organizationにおける
すべての管理権限を持つ。

主な対象：

- Organization管理
- Membership管理
- Role管理
- Project管理
- Production管理
- Participant管理
- 稽古管理
- タイムテーブル管理
- 会計管理
- 予算管理
- 予実管理
- 備品管理
- Document管理
- Announcement管理
- Regulation管理
- その他Organizationにおける管理機能

管理者は、
自身のOrganizationにおける
全Permissionを持つものとして扱う。

個別にすべてのPermissionを設定する必要はない。

基本構造：

Administrator
  ↓
All Organization Permissions

---

# Rehearsal Manager

稽古管理者。

稽古および稽古関連情報を
管理する権限を持つ。

主な対象：

- Rehearsal Candidate
- Rehearsal Availability
- Rehearsal
- Rehearsal Attendance
- Timetable
- Timetable Item

など。

稽古管理者は、
Organization全体の管理権限を持たない。

基本構造：

Rehearsal Manager
  ↓
Rehearsal Management Permissions

Rehearsal Managerは、
独立したEntityではない。

Roleとして定義され、
Membershipを通じてPersonへ付与される。

---

# Accounting Manager

会計管理者。

会計・予算・予実に関する
管理権限を持つ。

主な対象：

- Accounting Period
- Account
- Journal Entry
- Journal Entry Line
- Budget
- Budget Item
- Production Actual
- Budget vs Actual
- Production Settlement

など。

会計管理者は、
Organization全体の管理権限を持たない。

基本構造：

Accounting Manager
  ↓
Accounting Management Permissions

---

# Multiple Roles

一人のPersonに複数のRoleを付与できる。

例：

Person A
  ├── 管理者
  ├── 稽古管理者
  └── 会計管理者

RoleはMembershipを通じて
Organization Contextに適用される。

ただし、

管理者

を持つ場合、
Organizationの全権限を持つため、
他のRoleを追加する必要はない。

---

# Role and Membership

RoleはMembershipを通じて
Personへ付与される。

基本構造：

Person
  ↓
Membership
  ↓
Organization
  ↓
Role

RoleはPersonの属性ではない。

同じPersonでも、
Organizationごとに異なるRoleを持つことができる。

例えば、

Person A

  Membership A
    Organization = 劇団A
    Role = 管理者

  Membership B
    Organization = 劇団B
    Role = 稽古管理者

という構造になる。

Role変更は、
Membershipに対する変更として扱う。

---

# Organization Context

StageArtでは、
Personが利用するOrganization Contextによって、
適用されるRoleが決定される。

例：

Person A

  Organization A
    Role = 管理者

  Organization B
    Role = 会計管理者

Organization Aを利用している場合：

全Organization権限

Organization Bを利用している場合：

会計関連権限

のみが適用される。

RoleのScopeは、
そのMembershipが所属するOrganizationに限定される。

---

# Permission

Roleは、
Permissionのまとまりとして扱う。

基本構造：

Role
  ↓
Permission Set
  ↓
具体的な操作権限

例えば、

Rehearsal Manager
  ↓
Rehearsal Management Permissions

として、

- Rehearsal作成
- Rehearsal変更
- Rehearsal確定
- Rehearsalキャンセル
- Attendance管理
- Timetable管理

などの権限を持つ。

Accounting Managerについては、

Accounting Manager
  ↓
Accounting Management Permissions

として、

- Accounting Period管理
- Account管理
- Journal Entry管理
- Budget管理
- Production Actual管理
- Production Settlement管理

などの権限を持つ。

Permissionの具体的な実装方法は、
Role Domainの責務として管理する。

---

# Administrator Permission

Administratorは、
個別Permissionを一つずつ付与するのではなく、
Organizationにおける全Permissionを持つ。

基本構造：

Administrator
  ↓
All Organization Permissions

新しいOrganization機能が追加された場合も、
Administratorは原則としてその機能を利用できる。

---

# Permission Scope

Roleによって付与されるPermissionのScopeは、
Membershipが所属するOrganizationに限定される。

例えば、

Person
  ↓
Membership
  ↓
Organization A
  ↓
Role
  ↓
Permission

で得られるPermissionは、
Organization AのScopeにのみ適用される。

Organization Bの情報へ、
Organization AのRoleだけを利用して
アクセスすることはできない。

---

# Role and Participant Type

RoleとParticipant Typeは、
完全に別の概念として扱う。

## Role

Organizationにおける
管理・運営上の権限。

基本構造：

Membership
  ↓
Role

## Participant Type

Productionへの参加区分。

基本構造：

Production
  ↓
Participant
  ↓
Participant Type

Participant Typeには、
例えば以下を設定する。

- CAST
- STAFF

Roleは、

「そのOrganizationで何を管理できるか」

を表す。

Participant Typeは、

「そのProductionにどう関わっているか」

を表す。

---

# Example

一人のPersonが、

Organization
  劇団A
    Role = 管理者

Production
  公演A
    Participant Type = CAST

という状態になることができる。

また、

Organization
  劇団B
    Role = 稽古管理者

Production
  公演B
    Participant Type = STAFF

という状態になることもできる。

つまり、

Organization Role

と、

Production Participant Type

は独立して管理する。

---

# Role and Rehearsal

Rehearsalの管理権限は、
Roleによって付与される。

基本構造：

Person
  ↓
Membership
  ↓
Organization
  ↓
Role = Rehearsal Manager
  ↓
Rehearsal Management Permissions

稽古管理者は、
Rehearsalの管理を行うことができる。

ただし、
Rehearsal Managerという別Entityは作成しない。

---

# Role and Timetable

Timetableの管理権限も、
Roleによって付与される。

基本構造：

Person
  ↓
Membership
  ↓
Organization
  ↓
Role = Rehearsal Manager
  ↓
Timetable Management Permission

Participant Typeは、
Timetable Itemの対象者指定に利用する。

Participant Typeによって、
Timetableの編集権限を付与してはならない。

---

# Role and Accounting

Accountingの管理権限は、
Accounting Manager Roleによって付与される。

基本構造：

Person
  ↓
Membership
  ↓
Organization
  ↓
Role = Accounting Manager
  ↓
Accounting Management Permissions

Accounting Managerは、
会計領域を管理できる。

Accounting Managerであることだけで、
RehearsalやOrganization全体を
管理できるわけではない。

---

# Production Delegate

Production単位の権限付与は、
Organization Roleとは別に
ProductionDelegateで管理する。

基本構造：

Production
  ↓
Production Delegate
  ↓
Production Management Permission

Production Delegateは、
Organization全体のRoleを変更しない。

Production単位で必要な権限だけを
委任するために利用する。

例えば、

OrganizationのMembershipを持たないPersonへ、

Production A
  ↓
Production Delegate
  ↓
Production Management Permission

という形でProduction単位の権限を
委任できる。

---

# Organization Role and Production Delegate

Organization RoleとProduction Delegateは、
権限Scopeが異なる。

Organization Role：

Person
  ↓
Membership
  ↓
Organization
  ↓
Role
  ↓
Organization Permission

Production Delegate：

Person
  ↓
Production Delegate
  ↓
Production Permission

Organization Roleは、
Organization全体をScopeとする。

Production Delegateは、
特定ProductionをScopeとする。

---

# Information Sharing

情報共有の対象者を指定する際には、
Organization RoleとParticipant Typeを
必要に応じて参照できる。

例えば、

Announcement

  対象Role
    → 稽古管理者

  対象Participant Type
    → CAST

の場合、

- Organizationの稽古管理者
- ProductionのCAST

を対象として情報を共有できる。

別の例：

Announcement

  対象Role
    → 会計管理者

の場合は、
会計管理者のみを対象とする。

RoleとParticipant Typeは、
情報共有のために組み合わせて利用できる。

---

# Role Assignment

Roleの付与・変更は、
Organizationの管理権限を持つPersonが行う。

基本構造：

Administrator
  ↓
Membership
  ↓
Role Assignment

Role変更は、
Membershipに対する変更として扱う。

Role変更前の情報についても、
必要に応じて監査情報を保持できる。

---

# Role Removal

Roleを削除する場合、
PersonからRoleの適用を解除する。

Membershipそのものを削除する必要はない。

例えば、

Membership
  Organization = 劇団A
  Role = Accounting Manager

からAccounting Managerを解除しても、

Membership
  Organization = 劇団A

という所属関係自体は維持できる。

---

# Role Definition Lifecycle

Role自体は、
固定されたRole Definitionとして管理する。

基本Role：

- Administrator
- Rehearsal Manager
- Accounting Manager

PersonへのRole付与状態は、
Membership側で管理する。

Role Definitionそのものを
Personごとに複製しない。

---

# Custom Role

将来的に、
Organization独自のRoleを作成する必要が発生した場合は、
Role Definitionを拡張できる。

ただし、
初期実装ではCustom Roleを必須としない。

基本Roleを利用する。

---

# Permission Override

初期実装では、
個別Personに対するPermission Overrideを
必須としない。

基本構造は、

Role
  ↓
Permission Set

とする。

将来的に必要となった場合のみ、
Permission Overrideを追加する。

---

# Audit

Roleの付与・変更・解除について、
監査情報を保持できる。

例えば、

- AssignedBy
- AssignedAt
- ChangedBy
- ChangedAt
- RemovedBy
- RemovedAt

など。

具体的なAudit構造は、
共通Audit設計に従う。

Role Definitionの変更についても、
必要に応じて監査情報を保持する。

---

# Business Rules

- RoleはOrganization Contextに属する。
- RoleはPersonの属性ではない。
- RoleはMembershipを通じてPersonへ適用される。
- 同じPersonでもOrganizationごとに異なるRoleを持てる。
- RoleのPermission ScopeはMembershipのOrganizationに限定される。
- 管理者はOrganizationの全権限を持つ。
- 稽古管理者は稽古関連機能を管理できる。
- 会計管理者は会計・予算・予実関連機能を管理できる。
- 一人のPersonに複数Roleを付与できる。
- 管理者を持つPersonには、他のRoleを追加する必要はない。
- RoleはProductionへの参加区分を表さない。
- Productionへの参加区分はParticipant Typeで管理する。
- CASTとSTAFFはParticipant Typeとして管理する。
- RoleとParticipant Typeは独立して管理する。
- Participant TypeによってOrganization権限を付与しない。
- RoleによってProductionへの参加資格を自動付与しない。
- Rehearsal Managerは独立EntityではなくRoleとして扱う。
- Accounting Managerは独立EntityではなくRoleとして扱う。
- Timetable管理権限はRehearsal Manager RoleのPermissionとして扱う。
- Accounting管理権限はAccounting Manager RoleのPermissionとして扱う。
- Production単位の権限はProductionDelegateで管理する。
- Production DelegateはOrganization Roleを変更しない。
- Information SharingではRoleとParticipant Typeを参照できる。
- Roleの付与・変更はOrganizationの管理権限を持つPersonが行う。
- Role変更はMembershipに対する変更として扱う。
- Roleを解除してもMembershipそのものを削除する必要はない。
- Role DefinitionをPersonごとに複製しない。
- 初期実装ではCustom Roleを必須としない。
- 初期実装ではPermission Overrideを必須としない。

---

# Domain Events

Roleに関連する主なDomain Event：

- RoleAssigned
- RoleChanged
- RoleRemoved

Role Definition自体を変更する場合は、
Organizationの管理ルールに従う。

Role Eventは、
必要に応じてNotification、
Auditなどの関連Domainが利用できる。

---

# Event Meaning

RoleAssigned

MembershipにRoleが付与されたことを表す。

RoleChanged

Membershipに関連付けられたRoleが
変更されたことを表す。

RoleRemoved

MembershipからRoleが解除されたことを表す。

---

# Design Decisions

StageArtでは、
RoleをOrganization Contextにおける
権限のまとまりとして扱う。

基本Roleは、

- 管理者
- 稽古管理者
- 会計管理者

とする。

管理者はOrganizationの全権限を持つ。

稽古管理者は、
RehearsalおよびTimetableなどの
稽古関連機能を管理する。

会計管理者は、
Accounting、Budget、Production Actualなどの
会計関連機能を管理する。

RoleはMembershipを通じてPersonへ適用する。

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

RoleとParticipant Typeは明確に分離する。

RoleはOrganizationでの管理・運営上の権限。

Participant TypeはProductionでの参加区分。

また、
Organization全体のRoleと
Production単位の権限も分離する。

Organization Scope：

Membership
  ↓
Role
  ↓
Organization Permissions

Production Scope：

ProductionDelegate
  ↓
Production Permissions

この分離により、
Organization全体の管理権限を与えずに、
特定Productionだけを管理できる構造を実現する。

---

# Future

将来的に必要となった場合、

- より細かなPermission
- Organization独自Role
- Custom Role
- Permission Override
- Role Template
- Role変更履歴
- Roleごとの通知設定
- RoleごとのDashboard

などへ拡張できる。

ただし、
初期実装ではRoleを複雑化しない。

基本RoleとPermission Setによって
Organizationの権限体系を構築する。

---

# Design Principles

- RoleはOrganization Contextに属する。
- RoleはPersonの属性ではない。
- RoleはMembershipを通じて適用する。
- Organizationごとに異なるRoleを持てる。
- RoleのPermission ScopeはOrganizationに限定する。
- 管理者はOrganizationの全権限を持つ。
- 稽古管理者は稽古関連機能を管理する。
- 会計管理者は会計・予算・予実関連機能を管理する。
- 一人のPersonに複数Roleを付与できる。
- RoleとParticipant Typeを分離する。
- Participant TypeはProductionへの参加区分を表す。
- Participant TypeはOrganizationの権限を付与しない。
- RoleはProductionへの参加資格を自動付与しない。
- Rehearsal ManagerはRoleとして扱う。
- Accounting ManagerはRoleとして扱う。
- Timetable管理はRehearsal ManagerのPermissionとして扱う。
- Accounting管理はAccounting ManagerのPermissionとして扱う。
- Production単位の権限はProductionDelegateで管理する。
- Organization RoleとProduction Delegateを分離する。
- 情報共有ではRoleとParticipant Typeを参照できる。
- Role変更はMembershipに対する変更として扱う。
- Role DefinitionをPersonごとに複製しない。
- 初期実装ではRoleを必要以上に複雑化しない。
- Blueprintを唯一の設計基準とする。