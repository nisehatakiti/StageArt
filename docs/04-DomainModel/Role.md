# StageArt Blueprint

# Domain Model : Role

Version : 2.1

---

# Purpose

Roleは、
Personに付与される権限のまとまりを定義するDomainである。

RoleはPerson自身の属性ではない。

Roleは、
「何ができるか」を定義する。

Roleが誰に、
どのOrganizationまたはProductionのScopeで
適用されているかは、
RoleそのものではなくAssignmentによって管理する。

基本構造：

Role
  ↓
Permission Set

Role Assignment
  ↓
Role
  ↓
Scope

---

# Concept

Roleは、
Permissionのまとまりとして定義する。

RoleそのものはScopeを持たない。

例えば、

- Administrator
- Rehearsal Manager
- Accounting Manager
- Reservation Manager
- Participant Manager
- Performance Manager

などをRoleとして定義できる。

Roleは、
Personに直接所有されるものではない。

Role Definitionと、
RoleがPersonへ適用されている状態を分離する。

---

# Role Definition

Role Definitionは、
Roleが持つPermission Setを定義する。

基本構造：

Role
  ↓
Permission Set
  ↓
Permission

例えば、

Rehearsal Manager
  ↓
Rehearsal Management Permissions

として、

- Rehearsal.Read
- Rehearsal.Create
- Rehearsal.Update
- Rehearsal.Delete
- Timetable.Read
- Timetable.Create
- Timetable.Update

などを定義する。

Role Definitionは、
Personごとに複製しない。

---

# Scope

RoleはScopeを持たない。

Roleが実際に適用されるScopeは、
Role Assignmentによって決定する。

主なScopeは、

- Organization
- Production

とする。

例えば、

Role = Rehearsal Manager

を、

Organization Scopeで適用することもできる。

また、

Production Scopeで適用することもできる。

Roleそのものは、
どちらのScopeなのかを意識しない。

---

# Organization Role Assignment

OrganizationにおけるRoleは、
Membershipを通じてPersonへ適用する。

基本構造：

Person
  ↓
Membership
  ↓
Organization
  ↓
Role Assignment
  ↓
Role
  ↓
Permission

Membershipは、
PersonのOrganizationへの所属を表す。

Role Assignmentは、
そのMembershipに対して
どのRoleを付与しているかを表す。

---

# Membership and Role

MembershipとRoleは、
異なる責務を持つ。

Membership：

- Organizationへの所属
- 所属状態
- 所属開始
- 所属終了
- その他所属に関する情報

Role：

- Organization内で何ができるか

Role Assignment：

- そのMembershipにどのRoleを適用するか

基本構造：

Person
  ↓
Membership
  ↓
Organization
  ↓
Role Assignment
  ↓
Role

---

# Organization Roles

StageArtの基本Organization Roleは、
以下を提供する。

- Administrator
- Rehearsal Manager
- Accounting Manager

必要に応じて、
その他のOrganization Roleを追加できる。

---

# Administrator

Administratorは、
Organizationにおける全管理権限を持つRoleである。

主な対象：

- Organization管理
- Membership管理
- Role管理
- Project管理
- Production管理
- Participant管理
- Rehearsal管理
- Timetable管理
- Accounting管理
- Budget管理
- Production Actual管理
- Equipment管理
- Document管理
- Announcement管理
- Regulation管理
- その他Organizationにおける管理機能

Administratorは、
Organization Scopeにおける
全Permissionを持つ。

---

# Rehearsal Manager

Rehearsal Managerは、
稽古および稽古関連情報を管理するRoleである。

主なPermission：

- Rehearsal.Read
- Rehearsal.Create
- Rehearsal.Update
- Rehearsal.Delete
- Timetable.Read
- Timetable.Create
- Timetable.Update

必要に応じて、
Attendanceなどの稽古関連Permissionを含める。

Rehearsal Managerは、
独立したEntityではない。

Role Definitionとして管理する。

---

# Accounting Manager

Accounting Managerは、
会計・予算・予実に関する管理権限を持つRoleである。

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

Accounting Managerは、
Accounting関連Permissionを持つ。

Accounting Managerは、
Organization全体の管理権限を持つわけではない。

---

# Production Roles

Production単位でも、
Organization Roleと同じRole Definitionを利用できる。

例えば、

Role = Rehearsal Manager

を特定Productionに対して
Assignmentすることができる。

これにより、
Productionごとに異なる管理担当者を
設定できる。

Production専用の別Role Definitionを
作成する必要はない。

---

# ProductionDelegate

ProductionDelegateは、
特定Productionに対してRoleを
PersonへAssignmentするためのDomainである。

ProductionDelegateは、
Role Definitionではない。

ProductionDelegateは、
「誰に、どのProductionについて、
どのRoleを与えたか」
を表現する。

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
Production ScopeのRole Assignmentとして扱う。

---

# ProductionDelegate Example

例えば、

Production：

「12人のうかれる人々」

Person：

田中

Role：

Rehearsal Manager

の場合、

田中に対して、

Production
  ↓
ProductionDelegate
  ↓
Role = Rehearsal Manager

をAssignmentする。

これにより田中は、
このProductionについて、

- Rehearsalの参照
- Rehearsalの作成
- Rehearsalの変更
- Rehearsalの削除
- Timetableの管理

などのPermissionを持つ。

---

# Production Scope

ProductionDelegateによって
AssignmentされたRoleのPermissionは、
対象Productionに限定される。

例えば、

Person A
  ↓
ProductionDelegate
  ↓
Production A
  ↓
Role = Rehearsal Manager

の場合、

Person AはProduction Aについて
Rehearsal ManagerのPermissionを持つ。

Person Aが別のProduction Bについて
同じPermissionを持つとは限らない。

---

# ProductionDelegate and Organization Role

Organization RoleとProductionDelegateは、
AssignmentのScopeが異なる。

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

同じRole Definitionを、
異なるScopeで利用できる。

---

# ProductionDelegate Does Not Change Membership

ProductionDelegateによって
RoleをAssignmentしても、
PersonのMembershipは変更されない。

例えば、

Person A
  ↓
Membership
  ↓
Organization A
  ↓
Role = Member

という状態のPersonに対して、

Production A
  ↓
ProductionDelegate
  ↓
Role = Rehearsal Manager

をAssignmentすることができる。

この場合、

Organization AにおけるRole

と、

Production AにおけるRole

は別のAssignmentである。

---

# Role and Participant Type

RoleとParticipant Typeは、
完全に別の概念として扱う。

Role：

何ができるか。

Participant Type：

Productionにどう参加しているか。

基本構造：

Person
  ↓
Membership
  ↓
Organization
  ↓
Role Assignment
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

CASTであることは、
Roleではない。

STAFFであることも、
Roleではない。

Participant Typeによって、
管理権限を自動的に付与してはならない。

Roleによって、
Participant Typeを自動的に決定してはならない。

---

# Example : Cast

Person AがProduction Aに出演する場合：

Person A
  ↓
Participant
  ↓
Production A
  ↓
Participant Type = CAST

となる。

このことによって、
Person Aに管理権限が付与されるわけではない。

Person Aが稽古管理を担当する場合は、
別途、

Person A
  ↓
ProductionDelegate
  ↓
Production A
  ↓
Role = Rehearsal Manager

をAssignmentする。

---

# Example : Staff

Person BがProduction Aのスタッフである場合：

Person B
  ↓
Participant
  ↓
Production A
  ↓
Participant Type = STAFF

となる。

Person Bが予約管理を担当する場合は、

Person B
  ↓
ProductionDelegate
  ↓
Production A
  ↓
Role = Reservation Manager

をAssignmentする。

---

# Multiple Roles

一人のPersonに複数のRoleを
Assignmentできる。

例えば、

Person A
  ↓
ProductionDelegate
  ├── Role = Rehearsal Manager
  ├── Role = Reservation Manager
  └── Role = Participant Manager

という状態を作ることができる。

これにより、
一人のPersonが複数の管理領域を担当できる。

---

# Role Assignment and Scope

Role Assignmentは、
必ずScopeを持つ。

Organization Role Assignmentの場合：

Scope = Organization

Production Role Assignmentの場合：

Scope = Production

Role Definition自体には、
Scopeを持たせない。

これにより、
Role Definitionと
権限の適用範囲を分離する。

---

# Permission

Permissionは、
Roleが持つ具体的な操作権限を表す。

基本構造：

Role
  ↓
Permission Set
  ↓
Permission

例えば、

Rehearsal Manager
  ↓
Rehearsal Management Permission Set
  ↓
- Rehearsal.Read
- Rehearsal.Create
- Rehearsal.Update
- Rehearsal.Delete

など。

Permissionの具体的な粒度は、
Authorization設計に従う。

---

# Permission Scope

Permissionの有効範囲は、
Role AssignmentのScopeによって決定される。

例えば、

Organization Scopeで

Role = Rehearsal Manager

がAssignmentされている場合、

そのPermissionは
Organization内の対象Rehearsalに対して
適用される。

Production Scopeで

Role = Rehearsal Manager

がAssignmentされている場合、

そのPermissionは
AssignmentされたProductionに
限定される。

---

# Administrator Scope

Administratorは、
Organization ScopeでAssignmentされた場合、
Organization全体のPermissionを持つ。

基本構造：

Membership
  ↓
Role Assignment
  ↓
Role = Administrator
  ↓
All Organization Permissions

ProductionDelegateによって
Administrator RoleをProduction Scopeで
Assignmentすることも可能とする。

その場合、
AdministratorのPermissionは
対象ProductionのScopeに限定される。

つまり、

Role = Administrator

そのものが全権限なのではなく、

Role + Scope

によって実際の権限範囲が決定される。

---

# Role Assignment Lifecycle

Role Assignmentには、
AssignmentのLifecycleを持たせることができる。

基本的な情報：

- AssignedAt
- AssignedBy
- EffectiveFrom
- EffectiveUntil
- RemovedAt
- RemovedBy

具体的なAudit構造は、
共通Audit設計に従う。

---

# Role Assignment Removal

Role Assignmentを解除しても、
Role Definitionそのものは削除しない。

例えば、

Person A
  ↓
ProductionDelegate
  ↓
Production A
  ↓
Role = Rehearsal Manager

を解除した場合、

Rehearsal Manager Role自体は
引き続き存在する。

削除されるのは、
Person AへのAssignmentだけである。

---

# Role Definition Lifecycle

Role Definitionは、
OrganizationやPersonごとに複製しない。

Role Definitionを変更した場合、
そのRoleを利用しているAssignmentにも
影響する。

Role Definitionの変更については、
必要に応じてVersioningやAuditを行う。

---

# Custom Role

将来的に、
Organization独自のRoleを作成する必要が発生した場合は、
Custom Roleを追加できる。

ただし、
初期実装ではCustom Roleを必須としない。

基本Roleを利用する。

---

# Permission Override

初期実装では、
Person単位のPermission Overrideを
必須としない。

基本構造は、

Role
  ↓
Permission Set

とする。

個別Personの例外権限が必要になった場合のみ、
Permission Overrideを検討する。

---

# Role and ProductionDelegate

ProductionDelegateは、
Role Definitionを新しく定義するものではない。

ProductionDelegateは、
既存RoleをProduction Scopeへ
Assignmentするための仕組みである。

そのため、

DelegateRole

という別のRole Definitionは存在しない。

Role Definitionは、
Organization ScopeとProduction Scopeで
共通して利用する。

---

# Role and Organization Delegate

Organization Scopeにおいて、
ProductionDelegateと同様の
別Role Definitionを作成しない。

Organizationの通常権限は、

Membership
  ↓
Role Assignment
  ↓
Role

で管理する。

Production単位で追加権限を与える場合のみ、

ProductionDelegate
  ↓
Role

を利用する。

OrganizationとProductionで
Role Definitionを分裂させない。

---

# Authorization Model

StageArtの基本Authorization構造は、

Person
  ↓
Role Assignment
  ↓
Role
  ↓
Permission
  ↓
Scope

とする。

Organization Scopeでは、
MembershipがRole Assignmentの
所属Contextを提供する。

Production Scopeでは、
ProductionDelegateがRole Assignmentの
所属Contextを提供する。

---

# Business Rules

- RoleはPermissionのまとまりを定義する。
- RoleはPerson自身の属性ではない。
- Role DefinitionはScopeを持たない。
- Role DefinitionはPersonごとに複製しない。
- Role AssignmentによってRoleをPersonへ適用する。
- Organization ScopeのRole AssignmentはMembershipを通じて管理する。
- Production ScopeのRole AssignmentはProductionDelegateを通じて管理する。
- ProductionDelegateはRole Definitionではない。
- ProductionDelegateはProduction ScopeのRole Assignmentを表す。
- DelegateRoleという別のRole Definitionは作成しない。
- Organization RoleとProduction RoleでRole Definitionを分けない。
- 同じRole DefinitionをOrganization ScopeとProduction Scopeで利用できる。
- Role + Scopeによって実際のPermission範囲が決定される。
- 一人のPersonに複数のRoleをAssignmentできる。
- Participant TypeはRoleではない。
- CASTはRoleではなくParticipant Typeである。
- STAFFはRoleではなくParticipant Typeである。
- Participant Typeによって管理権限を自動付与しない。
- RoleによってParticipant Typeを自動決定しない。
- ProductionDelegateによるRole AssignmentはMembershipを変更しない。
- ProductionDelegateによるRole AssignmentはOrganization全体の権限を変更しない。
- Organization ScopeのAdministratorはOrganization全体のPermissionを持つ。
- Production ScopeのAdministratorは対象ProductionのScopeに限定される。
- Role Assignmentを解除してもRole Definitionは削除しない。
- 初期実装ではCustom Roleを必須としない。
- 初期実装ではPermission Overrideを必須としない。
- Role変更・Assignment変更は必要に応じてAudit対象とする。

---

# Domain Events

Roleに関連する主なDomain Event：

- RoleAssigned
- RoleChanged
- RoleRemoved

ProductionDelegateに関連する主なEvent：

- ProductionRoleAssigned
- ProductionRoleChanged
- ProductionRoleRemoved

Role Definition自体の変更については、
必要に応じてAuditする。

---

# Event Meaning

RoleAssigned

Organization Scopeまたはその他のRole Assignment Contextにおいて、
PersonへRoleがAssignmentされたことを表す。

RoleChanged

Role AssignmentにおけるRoleが
変更されたことを表す。

RoleRemoved

Role AssignmentからRoleが解除されたことを表す。

ProductionRoleAssigned

Production Scopeにおいて、
PersonへRoleがAssignmentされたことを表す。

ProductionRoleChanged

Production ScopeにおけるRole Assignmentの
Roleが変更されたことを表す。

ProductionRoleRemoved

Production ScopeからRole Assignmentが
解除されたことを表す。

---

# Design Decisions

StageArtでは、
RoleとDelegateRoleを別々のRole体系として管理しない。

Roleを唯一のRole Definitionとして扱う。

Roleは、

「何ができるか」

を定義する。

Role Assignmentは、

「誰に、どのScopeで、そのRoleを与えたか」

を表す。

Organization Scopeでは、

Person
  ↓
Membership
  ↓
Role Assignment
  ↓
Role
  ↓
Permission

という構造を使用する。

Production Scopeでは、

Person
  ↓
ProductionDelegate
  ↓
Role
  ↓
Permission

という構造を使用する。

ProductionDelegateは、
Production単位のRole Assignmentを表す。

ProductionDelegateは、
独立した権限体系を持たない。

DelegateRoleという別Role Definitionは作成しない。

Organization RoleとProduction Roleは、
同じRole Definitionを利用する。

これにより、

- Role Definitionの重複を防ぐ
- Permission Setの重複を防ぐ
- OrganizationとProductionの権限体系を統一する
- Participant TypeとRoleを明確に分離する
- Production単位の権限委任を実現する

ことができる。

---

# Example

Organization：

劇団A

Person：

田中

Organization Membership：

Role = Member

Production：

公演A

Participant：

Participant Type = CAST

Production Role Assignment：

Role = Rehearsal Manager

最終的な状態：

田中
  ├── Organization Membership
  │      └── Role = Member
  │
  ├── Production Participant
  │      └── Participant Type = CAST
  │
  └── ProductionDelegate
         └── Role = Rehearsal Manager

この状態により、

田中は劇団Aの一般メンバーであり、
公演AにはCASTとして参加し、
同時に公演Aの稽古管理を担当できる。

これら3つの意味を、
それぞれ別のDomainとして表現できる。

---

# Future

将来的に必要となった場合、

- より細かなPermission
- Custom Role
- Role Template
- Role Versioning
- Permission Override
- Role Assignment History
- Roleごとの通知設定
- RoleごとのDashboard
- より細かなScope

などへ拡張できる。

ただし、
初期実装ではRole体系を複雑化しない。

Role Definitionを一本化し、
Scopeによって権限の適用範囲を制御する。

---

# Design Principles

- Roleは「何ができるか」を定義する。
- RoleはPermission Setのまとまりである。
- Role DefinitionはScopeを持たない。
- Role DefinitionはPersonごとに複製しない。
- Role Assignmentは「誰に、どのScopeでRoleを与えたか」を表す。
- Organization ScopeのRole AssignmentはMembershipを通じて管理する。
- Production ScopeのRole AssignmentはProductionDelegateを通じて管理する。
- ProductionDelegateはRole Definitionではない。
- DelegateRoleという別Role Definitionを作成しない。
- Organization RoleとProduction Roleは同じRole Definitionを利用する。
- Role + Scopeによって実際のPermission範囲を決定する。
- Participant TypeとRoleを分離する。
- CAST / STAFFはParticipant TypeでありRoleではない。
- ProductionDelegateによるRole AssignmentはMembershipを変更しない。
- ProductionDelegateによるRole AssignmentはOrganization全体の権限を変更しない。
- Organization AdministratorはOrganization Scopeの全Permissionを持つ。
- Production ScopeでAssignmentされたAdministratorは対象Productionに限定される。
- Role Assignmentを解除してもRole Definitionは削除しない。
- 初期実装ではCustom Roleを必須としない。
- 初期実装ではPermission Overrideを必須としない。
- Blueprintを唯一の設計基準とする。