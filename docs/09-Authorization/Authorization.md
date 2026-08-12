# StageArt Blueprint

# Authorization

Version : 1.1

---

# Purpose

Authorizationは、
StageArtにおけるBusiness Resourceへのアクセス権限を定義する。

Authenticationによって利用者を識別した後、
Authorizationによって、
その利用者が対象Resourceに対して
どの操作を実行できるかを判定する。

StageArtでは、
Organization単位の権限と
Production単位の権限を分離する。

---

# Authorization Model

StageArtの認可は、
以下の二つの権限体系によって構成する。

Organization Membership
    ↓
Organization Role
    ↓
Permission Set

Production
    ├── PrimaryManager
    │       └── 全権限
    │
    └── ProductionDelegate
            └── Role
                    └── Permission Set

Organization Membershipは、
Organizationに対する所属および権限を表す。

PrimaryManagerおよびProductionDelegateは、
特定のProductionに対する管理権限を表す。

Roleは、
Organization ScopeおよびProduction Scopeで
共通して利用するPermission Setを定義する。

---

# Authentication

Authorizationは、
認証済みの利用者を対象として実行する。

未認証の利用者は、
公開Resourceを除き、
管理操作を実行できない。

UserAccountはAuthenticationを担当する。

PersonはBusiness上の人物を表す。

Authorizationでは、
認証されたUserAccountから対応するPersonを特定し、
そのPersonが持つ権限を判定する。

UserAccountとPersonは別のIdentityとして管理する。

AccountはAccounting Domainの勘定科目を表し、
Authenticationには使用しない。

---

# Authorization Scope

Authorizationには以下のScopeが存在する。

## Public Scope

一般公開されるResourceを対象とする。

例）

- 公開Production
- 公開Performance
- 公開Person
- 公開Participant

公開設定によって、
認証なしで参照できる場合がある。

---

## Organization Scope

Organization Membershipによって
Organization単位の権限を判定する。

基本構造：

Organization
    ↓
Membership
    ↓
Person
    ↓
Role
    ↓
Permission

Membershipは、
PersonとOrganizationの所属関係を表す。

RoleはOrganization Scopeにおける
Personの権限を定義する。

Organization ScopeのRoleは、
そのOrganizationにおいて有効である。

---

## Production Scope

Productionに対する管理権限を判定する。

Production
    ├── PrimaryManager
    │
    └── ProductionDelegate
            └── Role
                    └── Permission

Production Scopeの権限は、
そのProductionに対してのみ有効である。

ProductionDelegateに適用されるRoleは、
Organization Scopeで使用されるRoleと
同じRole Definitionを利用する。

---

# Organization Membership

Membershipは、
PersonとOrganizationの所属関係を表す。

MembershipにはOrganization単位のRoleを設定できる。

Organization Roleは、
Organization全体に対する権限を表す。

例えば、

- ORGANIZATION_ADMIN
- ORGANIZATION_MEMBER

などを定義できる。

具体的なOrganization Roleは、
Role Domainで定義する。

Membershipによって適用されたRoleは、
そのOrganization Scopeにおいて有効である。

Organization Membershipが存在すること自体は、
Production Scopeの管理権限を意味しない。

---

# Role

Roleは、
複数のPermissionをまとめたPermission Setを定義する。

RoleはPersonへ直接付与しない。

RoleはMembershipまたはProductionDelegateを介して
Personへ適用する。

基本構造：

Role
    ↓
Permission Set
    ↓
Permission

RoleはOrganization ScopeとProduction Scopeの
両方で利用できる。

例えば、

REHEARSAL_MANAGER
    ↓
Rehearsal.Read
Rehearsal.Create
Rehearsal.Update
Rehearsal.Delete
Schedule.Read

RESERVATION_MANAGER
    ↓
Reservation.Read
Reservation.Create
Reservation.Update
Reservation.Cancel
Reservation.CheckIn
Performance.Read

PARTICIPANT_MANAGER
    ↓
Participant.Read
Participant.Create
Participant.Update
Participant.Delete

PERFORMANCE_MANAGER
    ↓
Performance.Read
Performance.Create
Performance.Update
Performance.Cancel
Performance.Finish

RoleはPermission Setの定義であり、
Person固有の属性ではない。

同じRoleを複数のPersonへ適用できる。

---

# Role Application

RoleをPersonへ適用する経路は、
Scopeによって異なる。

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

RoleAssignmentという独立Domainは作成しない。

RoleをどのScopeで誰に適用するかは、
MembershipまたはProductionDelegateによって表現する。

---

# Production Primary Manager

Productionには一人のPrimaryManagerを設定する。

PrimaryManagerは、
Productionに関する全権限を持つ。

PrimaryManagerはRoleによる制限を受けない。

Production
    ↓
PrimaryManager
    ↓
Person
    ↓
All Production Permissions

PrimaryManagerは、
Productionに紐づく管理対象を横断して操作できる。

PrimaryManagerはProductionDelegateとは異なる。

PrimaryManagerには、
ProductionDelegate用のRoleを適用する必要はない。

---

# Production Delegate

ProductionDelegateは、
Productionの管理権限を委任されたPersonを表す。

基本構造：

Production
    ↓
ProductionDelegate
    ├── Person
    └── Role

ProductionDelegateは、
Roleに定義されたPermissionのみを持つ。

Roleに含まれない操作は実行できない。

ProductionDelegateは、
Production単位で管理する。

同一Personが複数のProductionで
ProductionDelegateになることができる。

また、同一Personに対して、
Productionごとに異なるRoleを適用できる。

---

# Permission

Permissionは、
特定のResourceに対して実行可能な操作を表す。

基本形式は、

Resource.Action

とする。

例）

Production.Read
Production.Create
Production.Update
Production.Publish
Production.Archive

Performance.Read
Performance.Create
Performance.Update
Performance.Cancel

Participant.Read
Participant.Create
Participant.Update
Participant.Delete

Reservation.Read
Reservation.Create
Reservation.Update
Reservation.Cancel
Reservation.CheckIn

Rehearsal.Read
Rehearsal.Create
Rehearsal.Update
Rehearsal.Delete

Schedule.Read
Schedule.Create
Schedule.Update
Schedule.Delete

Task.Read
Task.Create
Task.Update
Task.Delete

Document.Read
Document.Create
Document.Update
Document.Delete

---

# Permission Actions

基本的なActionは以下とする。

- Read
- Create
- Update
- Delete

Business Resourceによっては、
専用Actionを追加する。

例）

Production.Publish
Production.Archive

Performance.Publish
Performance.Cancel
Performance.Finish

Reservation.CheckIn
Reservation.Cancel

専用Actionは、
単純なCRUDでは表現できないBusiness Operationに対して使用する。

---

# Permission Set

Roleは複数のPermissionをまとめて保持する。

例えば、

REHEARSAL_MANAGER
    ↓
Rehearsal.Read
Rehearsal.Create
Rehearsal.Update
Rehearsal.Delete
Schedule.Read

RESERVATION_MANAGER
    ↓
Reservation.Read
Reservation.Create
Reservation.Update
Reservation.Cancel
Reservation.CheckIn
Performance.Read

PARTICIPANT_MANAGER
    ↓
Participant.Read
Participant.Create
Participant.Update
Participant.Delete

PERFORMANCE_MANAGER
    ↓
Performance.Read
Performance.Create
Performance.Update
Performance.Cancel
Performance.Finish

これらはRoleとして定義する。

---

# Authorization Decision

対象Resourceに対する操作可否は、
以下の順序で判定する。

1. Authentication

    ↓

認証済み利用者であることを確認する。

2. Resource Scope

    ↓

対象ResourceがどのOrganizationまたはProductionに属するかを確認する。

3. PrimaryManager

    ↓

対象ProductionのPrimaryManagerである場合、
Productionに関する全権限を持つ。

4. ProductionDelegate

    ↓

対象ProductionのProductionDelegateである場合、
適用されているRoleを取得する。

5. Role

    ↓

Roleに対象Permissionが存在するか確認する。

6. Permission

    ↓

Permissionが存在する場合は操作を許可する。

存在しない場合は操作を拒否する。

---

# Decision Flow

概念的には以下の流れで判定する。

Request
    ↓
Authenticated?
    │
    ├── No
    │     ↓
    │   Reject
    │
    └── Yes
          ↓
      Target Resource
          ↓
      Production Scope?
          │
          ├── No
          │     ↓
          │   Check Organization Scope
          │
          └── Yes
                ↓
          PrimaryManager?
                │
                ├── Yes
                │     ↓
                │   Allow
                │
                └── No
                      ↓
                ProductionDelegate?
                      │
                      ├── No
                      │     ↓
                      │   Check Organization Scope
                      │
                      └── Yes
                            ↓
                           Role
                            ↓
                       Permission?
                            │
                            ├── Yes
                            │     ↓
                            │   Allow
                            │
                            └── No
                                  ↓
                                Reject

---

# PrimaryManager Priority

PrimaryManagerは、
Productionに関する全権限を持つ。

したがって、
RoleのPermission Setを
PrimaryManagerの権限判定に使用しない。

PrimaryManager
    ↓
All Production Permissions

ProductionDelegate
    ↓
Role
    ↓
Permission Set

この二つの権限経路を分離する。

---

# Production Scope

Production単位の権限は、
対象Productionに対してのみ有効とする。

例えば、

Person A

Production A
    ↓
PrimaryManager

Production B
    ↓
権限なし

の場合、

Person AはProduction Aを管理できるが、
Production Bを管理できない。

---

# Production Delegate Scope

ProductionDelegateの権限も、
登録されたProductionに対してのみ有効とする。

例えば、

Person A

Production A
    ↓
ProductionDelegate
    ↓
REHEARSAL_MANAGER

Production B
    ↓
ProductionDelegate
    ↓
RESERVATION_MANAGER

の場合、

Production Aでは
Rehearsal関連の権限を持つ。

Production Bでは
Reservation関連の権限を持つ。

Production Aで持つ権限が
Production Bへ自動的に継承されることはない。

同一Personに対するRoleは、
Productionごとに独立して判定する。

---

# Organization and Production Authorization

Organization MembershipとProduction単位の権限は、
別の概念として管理する。

Organization Membership
    ↓
Organization Scope
    ↓
Role
    ↓
Permission

PrimaryManager
    ↓
Production Scope
    ↓
All Production Permissions

ProductionDelegate
    ↓
Production Scope
    ↓
Role
    ↓
Permission

Organization Membershipによって
自動的にProductionDelegateになることはない。

ProductionDelegateであることによって、
Organization MembershipのRoleが変更されることもない。

---

# Cross Scope Access

Organization ScopeとProduction Scopeが
同一Requestに関係する場合は、
対象ResourceのDomain Ruleに従って判定する。

例えば、

Organization A
    ↓
Project A
    ↓
Production A
    ↓
Reservation A

という関係において、
Reservation Aを操作するには、
Reservationが所属するPerformance、
Performanceが所属するProductionを特定し、
Productionに対する管理権限を確認する。

必要に応じて、
Organization Scopeの権限も確認する。

---

# Resource Ownership

Authorizationでは、
対象ResourceがどのScopeに属するかを
明確にする。

例）

Production
    ↓
Production Scope

Performance
    ↓
Production Scope

Participant
    ↓
Production Scope

Reservation
    ↓
Performance
    ↓
Production Scope

Rehearsal
    ↓
Production Scope

Schedule
    ↓
Production Scope

Task
    ↓
Production Scope

Document
    ↓
Production Scope

この構造により、
ProductionDelegateのRoleによるPermissionを
関連Resourceへ適用できる。

---

# Reservation Authorization

ReservationはPerformanceに属する。

そのためReservationに対する管理権限は、
Reservationが所属するPerformanceから
Production Scopeを解決して判定する。

Production
    ↓
Performance
    ↓
Reservation

例えば、

Reservation.Read
Reservation.Create
Reservation.Update
Reservation.Cancel
Reservation.CheckIn

などのPermissionをRoleへ設定できる。

---

# Check In Authorization

Check InはReservation単位のBusiness Operationである。

Check In権限は、

Reservation.CheckIn

として定義する。

Check Inを行う利用者は、
対象Reservationが所属するPerformanceを特定できなければならない。

受付開始時には、

Production
    ↓
Performance

を選択する。

その後、
選択されたPerformanceに属するReservationのみを
Check In対象とする。

別のPerformanceに属するReservationは
Check Inできない。

---

# Check In Scope Validation

Check Inでは、
以下を確認する。

1. 利用者が認証済みである。
2. 対象Productionを特定できる。
3. 対象Performanceを特定できる。
4. 利用者が対象Productionに対するCheck In権限を持つ。
5. Reservationが対象Performanceに属している。
6. ReservationがすでにCHECKED_INではない。
7. ReservationがCANCELLEDではない。

いずれかを満たさない場合、
Check Inを実行しない。

---

# Rehearsal Authorization

将来的な稽古管理では、
RehearsalをProduction ScopeのResourceとして扱う。

Production
    ↓
Rehearsal

ProductionDelegateには、
Roleによって以下のようなPermissionを付与できる。

Rehearsal.Read
Rehearsal.Create
Rehearsal.Update
Rehearsal.Delete

PrimaryManagerは
Rehearsalに対する全権限を持つ。

---

# Schedule Authorization

ScheduleもProduction ScopeのResourceとして扱う。

Production
    ↓
Schedule

Roleには、

Schedule.Read
Schedule.Create
Schedule.Update
Schedule.Delete

などを設定できる。

---

# Participant Authorization

ParticipantはProductionに属する。

Production
    ↓
Participant

Roleには、

Participant.Read
Participant.Create
Participant.Update
Participant.Delete

などを設定できる。

Participantであること自体は、
Participantの管理権限を意味しない。

---

# Performance Authorization

PerformanceはProductionに属する。

Production
    ↓
Performance

Roleには、

Performance.Read
Performance.Create
Performance.Update
Performance.Cancel
Performance.Finish

などを設定できる。

PrimaryManagerはPerformanceに関する全権限を持つ。

---

# Permission Independence

Permissionは、
Personそのものに直接付与しない。

Organization Scope：

Person
    ↓
Membership
    ↓
Role
    ↓
Permission

Production Scope：

Person
    ↓
ProductionDelegate
    ↓
Role
    ↓
Permission

という経路で権限を解決する。

これにより、
同一PersonがOrganizationごと、
またProductionごとに異なる権限を持つことができる。

---

# Role Independence

RoleはPersonそのものの属性ではない。

Person Aが、

Production A
    ↓
REHEARSAL_MANAGER

Production B
    ↓
RESERVATION_MANAGER

となることを許可する。

Person A自身に
REHEARSAL_MANAGERというRoleが固定的に付いているわけではない。

Roleは、
MembershipまたはProductionDelegateを介して
Scopeごとに適用される。

---

# Role Scope Isolation

Roleによって付与されたPermissionは、
Roleが適用されたScope内でのみ有効である。

Organization ScopeのRole：

Organization A
    ↓
Membership
    ↓
Person A
    ↓
Role
    ↓
Permission

Production ScopeのRole：

Production A
    ↓
ProductionDelegate
    ↓
Person A
    ↓
Role
    ↓
Permission

Organization AのRoleが、
自動的にProduction AのProductionDelegate権限へ
変換されることはない。

同様に、
ProductionDelegateのRoleが
Organization Scopeへ自動的に適用されることもない。

---

# Role and Permission Management

Roleの定義は、
Authorization Domainで管理する。

Permissionは、
Business Resourceに対する操作単位で定義する。

RoleはPermissionの集合として定義する。

Roleの変更によって、
そのRoleを適用されているPersonの権限が変更される。

Roleの適用関係は、
MembershipまたはProductionDelegateによって管理する。

RoleAssignmentという独立Entityは作成しない。

---

# Authorization Boundary

Authorizationは、
「誰が」「どのScopeで」「何を」「どの操作まで」
実行できるかを判定する。

基本構造：

UserAccount
    ↓
Person
    ↓
Scope
    ↓
Role
    ↓
Permission
    ↓
Resource.Action

Production Scopeでは、

Person
    ↓
ProductionDelegate
    ↓
Role
    ↓
Permission
    ↓
Resource.Action

という経路で判定する。

PrimaryManagerの場合は、

Person
    ↓
PrimaryManager
    ↓
All Production Permissions
    ↓
Resource.Action

となる。

---

# Security Principles

- AuthenticationとAuthorizationを分離する。
- UserAccountはAuthentication Identityとして扱う。
- PersonはBusiness Identityとして扱う。
- AccountはAccounting Domainの勘定科目として扱う。
- AccountをAuthentication Identityとして使用しない。
- Organization ScopeとProduction Scopeを分離する。
- RoleはPermission Setを定義する。
- RoleはPersonへ直接付与しない。
- MembershipまたはProductionDelegateを介してRoleを適用する。
- RoleAssignmentという独立Domainを作成しない。
- ProductionDelegateはProduction Scopeに限定する。
- Organization ScopeのRoleをProduction Scopeへ自動継承しない。
- Production ScopeのRoleをOrganization Scopeへ自動継承しない。
- PrimaryManagerはProductionに対する全権限を持つ。
- ProductionDelegateはRoleに定義されたPermissionのみを持つ。
- PermissionはResource.Action形式で定義する。
- Resource Scopeを必ず確認してからPermissionを判定する。
- 異なるOrganizationのResourceへ無断でアクセスできない。
- 異なるProductionのResourceへ無断でアクセスできない。
- Public Scopeでは公開設定に従って認証なしの参照を許可できる。
- 認証情報をAuthorization Resourceとして公開しない。
- CredentialをPermissionやRoleの内容として公開しない。
- Authorizationの具体的な実装はInfrastructure Layerへ依存しない。
- Blueprintを唯一の設計基準とする。