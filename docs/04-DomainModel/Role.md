# StageArt Blueprint

# Domain Model : Role

Version : 3.0

---

# Purpose

Roleは、
StageArtにおける権限のまとまりを定義するDomainである。

RoleはPerson自身の属性ではない。

Roleは、
「何ができるか」
を定義する。

Roleが誰に、
どのScopeで適用されているかは、
Roleそのものではなく、
MembershipまたはProductionDelegateとの関係によって決定する。

基本構造：

Role
    ↓
Permission Set
    ↓
Permission

Roleの適用：

Organization Scope
    Person
        ↓
    Membership
        ↓
    Organization
        ↓
    Role
        ↓
    Permission

Production Scope
    Person
        ↓
    ProductionDelegate
        ↓
    Production
        ↓
    Role
        ↓
    Permission

RoleAssignmentという独立Domainは作成しない。

DelegateRoleという別のRole体系も使用しない。

---

# Concept

Roleは、
複数のPermissionをまとめた
Permission Setを定義する。

Roleそのものは、
OrganizationやProductionなどのScopeを持たない。

Roleは、
どのScopeで使用されるかを意識せず、
権限の内容だけを定義する。

例えば、

- Administrator
- Rehearsal Manager
- Accounting Manager
- Reservation Manager
- Participant Manager
- Performance Manager

などをRoleとして定義できる。

Role Definitionは、
Personごとに複製しない。

同じRole Definitionを、

- Organization Scope
- Production Scope

の両方で利用できる。

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
PersonやMembershipごとに複製しない。

Role Definitionを変更した場合、
そのRoleを適用しているScopeのPermissionにも反映される。

---

# Scope

Role Definition自身は、
Scopeを持たない。

Roleが実際にどのScopeで有効になるかは、
Roleを適用する関係によって決定する。

StageArtでは、
主に以下のScopeを使用する。

- Organization Scope
- Production Scope

Organization Scopeでは、
Membershipを通じてRoleを適用する。

Production Scopeでは、
ProductionDelegateを通じてRoleを適用する。

Roleそのものに、

- OrganizationId
- ProductionId
- ScopeType

などを持たせない。

---

# Organization Scope

Organization ScopeにおけるRoleは、
Membershipを通じてPersonへ適用する。

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

Membershipは、
PersonとOrganizationの所属関係を表す。

Roleは、
そのMembershipを通じて
Organization ScopeにおけるPersonの権限を決定する。

例えば、

Person A
    ↓
Membership
    ↓
Organization A
    ↓
Role = Administrator

の場合、

Person AはOrganization Aにおいて
AdministratorのPermissionを持つ。

---

# Membership and Role

MembershipとRoleは、
それぞれ異なる責務を持つ。

Membership：

- Organizationへの所属
- 所属状態
- 所属開始
- 所属終了
- Organization内でのRole

Role：

- OrganizationまたはProductionで利用するPermission Set

Membershipは、
Role Definitionそのものを定義しない。

Membershipは、
既存のRole Definitionを
Organization ScopeにおいてPersonへ適用する。

RoleAssignmentという独立Entityは作成しない。

---

# Organization Role

StageArtでは、
Organization Scopeにおける基本Roleを定義できる。

例：

- Administrator
- Rehearsal Manager
- Accounting Manager
- Reservation Manager
- Participant Manager
- Performance Manager

必要に応じて、
Organizationの運営に必要なRoleを追加できる。

具体的なPermissionは、
Authorization Domainで定義する。

---

# Administrator

Administratorは、
Organization Scopeにおける
全般的な管理権限を持つRoleである。

主な対象：

- Organization管理
- Membership管理
- Project管理
- Production管理
- Participant管理
- Rehearsal管理
- Reservation管理
- Performance管理
- Accounting管理
- Document管理
- Announcement管理
- その他Organization Scopeの管理対象

具体的なPermissionは、
Authorization Domainで定義する。

---

# Organization Owner

Organization Ownerは、
Organization Scopeにおける
全権限を持つ管理者として扱う。

Organization Ownerという独立したRole体系は作成しない。

Owner情報は、
Membershipに適用されたRoleによって表現する。

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role = Administrator
    ↓
Permission

したがって、
Organization自身にOwnerIdを直接保持しない。

Ownerを変更する場合も、
Membershipに適用されているRoleの変更として管理できる。

Organization OwnerとProduction PrimaryManagerは、
異なる概念として扱う。

Organization Owner：

Organization Scopeの管理者

PrimaryManager：

Production Scopeの管理者

---

# Production Scope

Production ScopeにおけるRoleは、
ProductionDelegateを通じてPersonへ適用する。

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
特定Productionに対して
PersonへRoleを適用する関係を表す。

ProductionDelegate自身は、
Permissionを定義しない。

Permissionは、
ProductionDelegateが参照するRoleによって決定する。

---

# Production Delegate

ProductionDelegateは、
特定Productionに対して
PersonへRoleを適用する。

例えば、

Production A
    ↓
ProductionDelegate
    ├── Person A
    └── Role = Rehearsal Manager

の場合、

Person AはProduction Aについて
Rehearsal ManagerのPermissionを持つ。

同じPersonを、
複数のProductionへ
ProductionDelegateとして登録できる。

例えば、

Production A
    ↓
ProductionDelegate
    ├── Person A
    └── Role = Rehearsal Manager

Production B
    ↓
ProductionDelegate
    ├── Person A
    └── Role = Reservation Manager

という状態を許可する。

ProductionDelegateによって適用されたRoleは、
そのProduction Scopeにおいてのみ有効である。

---

# PrimaryManager

Productionには、
PrimaryManagerが存在する。

PrimaryManagerは、
Productionに関する全管理権限を持つ。

PrimaryManagerは、
ProductionDelegateとは異なる。

PrimaryManager：

Production Scopeにおける
全管理権限を持つ。

ProductionDelegate：

Roleによって限定された
Production Scopeの権限を持つ。

基本構造：

Production
    ↓
PrimaryManager
    ↓
Person

Production
    ↓
ProductionDelegate
    ├── Person
    └── Role
            ↓
        Permission

PrimaryManagerは、
Organization Ownerとは異なる。

Organization Ownerは、
Organization Scopeの管理者である。

PrimaryManagerは、
Production Scopeの管理者である。

---

# Role and Participant Type

RoleとParticipant Typeは、
明確に分離する。

Role：

管理・運営上の権限を表す。

Participant Type：

Productionへの参加区分を表す。

例えば、

Participant Type = CAST

であっても、
Productionの管理権限は自動的に付与されない。

Participant Type = STAFF

であっても、
Productionの管理権限は自動的に付与されない。

Productionの管理権限が必要な場合は、
ProductionDelegateによってRoleを適用する。

基本構造：

Production
    ↓
Participant
    ↓
Participant Type

一方、

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

Roleによって、
Productionへの参加区分を自動的に決定してはならない。

Participant Typeによって、
管理権限を自動的に付与してはならない。

---

# Role Reuse

Role Definitionは、
Organization ScopeとProduction Scopeで共通して利用できる。

例えば、

Rehearsal Manager

というRole Definitionを、

Organization Scope：

Person A
    ↓
Membership
    ↓
Organization A
    ↓
Role = Rehearsal Manager

として適用することもできる。

また、

Production Scope：

Person B
    ↓
ProductionDelegate
    ↓
Production A
    ↓
Role = Rehearsal Manager

として適用することもできる。

この場合、
Role Definition自体は同一である。

ScopeによってRole Definitionを複製しない。

---

# Role Independence

Roleは、
Personに直接所有されない。

Person自身にRoleを設定しない。

例えば、

Person
    ↓
Role

という直接関係は作成しない。

Roleは、

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

という関係によって適用する。

これにより、
同じPersonがOrganizationごと、
Productionごとに異なる権限を持つことができる。

---

# Permission

Permissionは、
Business Resourceに対して
実行可能な操作を表す。

Permissionの詳細な定義は、
Authorization Domainで管理する。

例：

- Rehearsal.Read
- Rehearsal.Create
- Rehearsal.Update
- Rehearsal.Delete
- Timetable.Read
- Timetable.Create
- Timetable.Update
- Participant.Read
- Participant.Create
- Participant.Update
- Reservation.Read
- Reservation.Create
- Reservation.Update
- Reservation.Cancel
- Reservation.CheckIn

Roleは、
これらのPermissionをまとめた
Permission Setを定義する。

---

# Authorization Relationship

StageArtにおけるRole適用の基本構造は以下とする。

## Organization Scope

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

## Production Scope

Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

## PrimaryManager

Production
    ↓
PrimaryManager
    ↓
Person
    ↓
全Production Permission

PrimaryManagerは、
Roleによる限定権限ではなく、
Production Scopeの全管理権限を持つ。

---

# No RoleAssignment

StageArtでは、
RoleAssignmentという独立Domain Entityを作成しない。

Roleの適用関係は、
Scopeごとの既存Entityによって表現する。

Organization Scope：

Membership

Production Scope：

ProductionDelegate

したがって、

Person
    ↓
RoleAssignment
    ↓
Role

という構造は使用しない。

---

# No DelegateRole

StageArtでは、
DelegateRoleという別のRole体系を作成しない。

Organization Delegateや
Production Delegateのために、
Role Definitionを別体系として複製しない。

すべてのRoleは、
共通のRole Definitionを利用する。

Organization Scope：

Membership
    ↓
Role

Production Scope：

ProductionDelegate
    ↓
Role

という構造に統一する。

---

# Lifecycle

Role Definition自体のLifecycleは、
Role Domainで管理する。

RoleがOrganizationまたはProductionから
参照されている場合、
Role Definitionの変更が
既存のRole適用へ影響する可能性がある。

そのため、
Role Definitionの変更・廃止については、
Authorization Domainで定義する
PermissionおよびRole管理ルールに従う。

Roleを削除する場合は、
既存のMembershipやProductionDelegateから
参照されている状態を考慮する。

---

# Boundary

Role Domainは、
以下を管理する。

- Role Definition
- Role Name
- Role Description
- Permission Set
- RoleのLifecycle
- Role Definitionの有効状態

Role Domainは、
以下を管理しない。

- Personへの直接的なRole付与
- Organizationへの所属
- Productionへの参加
- Production Scopeの委任関係
- Permissionの認可判定そのもの

OrganizationへのRole適用はMembershipが管理する。

ProductionへのRole適用はProductionDelegateが管理する。

Permissionの詳細な定義と認可判定は、
Authorization Domainで管理する。

---

# Summary

StageArtにおけるRoleの基本構造は、

Role
    ↓
Permission Set
    ↓
Permission

である。

Organization Scopeでは、

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

とする。

Production Scopeでは、

Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

とする。

Productionの全管理権限は、

Production
    ↓
PrimaryManager
    ↓
Person

によって表現する。

RoleはScopeを持たない。

RoleはPersonに直接付与しない。

RoleAssignmentという独立Domainは作成しない。

DelegateRoleという別のRole体系は使用しない。

同じRole Definitionを、
Organization ScopeとProduction Scopeの両方で利用する。

RoleとParticipant Typeは、
明確に分離する。