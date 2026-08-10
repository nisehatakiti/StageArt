# StageArt Blueprint
# Authorization

Version : 1.0

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
Organization単位の権限

Production
    ├── PrimaryManager
    │       └── 全権限
    │
    └── ProductionDelegate
            └── DelegateRole
                    └── Permission Set

Organization Membershipは、
Organizationに対する所属および権限を表す。

PrimaryManagerおよびProductionDelegateは、
特定のProductionに対する管理権限を表す。

---

# Authentication

Authorizationは、
認証済みの利用者を対象として実行する。

未認証の利用者は、
公開Resourceを除き、
管理操作を実行できない。

AccountはAuthenticationを担当する。

PersonはBusiness上の人物を表す。

Authorizationでは、
認証されたAccountから対応するPersonを特定し、
そのPersonが持つ権限を判定する。

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

例）

Organization
    ↓
Membership
    ↓
Person

Organization Membershipは、
Organizationに対する操作権限を表す。

---

## Production Scope

Productionに対する管理権限を判定する。

Production
    ├── PrimaryManager
    │
    └── ProductionDelegate
            └── DelegateRole

Production Scopeの権限は、
そのProductionに対してのみ有効である。

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
Organization Authorization設計で定義する。

---

# Production Primary Manager

Productionには一人のPrimaryManagerを設定する。

PrimaryManagerは、
Productionに関する全権限を持つ。

PrimaryManagerはDelegateRoleによる制限を受けない。

Production
    ↓
PrimaryManager
    ↓
Person
    ↓
All Production Permissions

PrimaryManagerは、
Productionに紐づく管理対象を横断して操作できる。

---

# Production Delegate

ProductionDelegateは、
Productionの管理権限を委任されたPersonを表す。

Production
    ↓
ProductionDelegate
    ├── Person
    └── DelegateRole

ProductionDelegateは、
DelegateRoleに定義されたPermissionのみを持つ。

DelegateRoleに含まれない操作は実行できない。

---

# DelegateRole

DelegateRoleは、
ProductionDelegateへ付与する権限セットを定義するMaster Dataである。

DelegateRoleはPersonへ直接付与しない。

DelegateRoleはProductionDelegateを介して適用する。

Person
    ↑
ProductionDelegate
    ↑
DelegateRole

同一Personが、
Productionごとに異なるDelegateRoleを持つことを許可する。

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

DelegateRoleは複数のPermissionをまとめて保持する。

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
DelegateRoleを取得する。

5. DelegateRole

    ↓

DelegateRoleに対象Permissionが存在するか確認する。

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
          │   Other Authorization
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
                       DelegateRole
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
DelegateRoleのPermission Setを
PrimaryManagerの権限判定に使用しない。

PrimaryManager
    ↓
All Production Permissions

ProductionDelegate
    ↓
DelegateRole
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

---

# Organization and Production Authorization

Organization MembershipとProduction単位の権限は、
別の概念として管理する。

Organization Membership
    ↓
Organization Scope

PrimaryManager
    ↓
Production Scope

ProductionDelegate
    ↓
Production Scope

Organization Membershipによって
自動的にProductionDelegateになることはない。

ProductionDelegateであることによって、
Organization Membershipの権限が変更されることもない。

---

# Cross Scope Access

Organization ScopeとProduction Scopeが
同一Requestに関係する場合は、
対象ResourceのDomain Ruleに従って判定する。

例えば、

Organization A
    ↓
Production A
    ↓
Reservation A

という関係において、
Reservation Aを操作するには、
Reservationが所属するPerformance、
Performanceが所属するProductionを特定し、
Productionに対する管理権限を確認する。

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
ProductionDelegateの権限を
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

などのPermissionをDelegateRoleへ設定できる。

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
DelegateRoleによって以下のような権限を付与できる。

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

DelegateRoleには、

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

DelegateRoleには、

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

DelegateRoleには、

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

Person
    ↓
ProductionDelegate
    ↓
DelegateRole
    ↓
Permission

という経路で権限を解決する。

これにより、
同一PersonがProductionごとに異なる権限を持つことができる。

---

# Role Independence

DelegateRoleはPersonのRoleではない。

Person Aが、

Production A
    ↓
REHEARSAL_MANAGER

Production B
    ↓
RESERVATION_MANAGER

となることを許可する。

Person A自身に
REHEARSAL_MANAGERというRoleが付いているわけではない。

---

# Role Status

DelegateRoleにはStatusを持たせる。

ACTIVE
INACTIVE

ACTIVEのDelegateRoleは、
新しいProductionDelegateへ設定できる。

INACTIVEのDelegateRoleは、
新規のProductionDelegateへ設定できない。

既存ProductionDelegateへの影響については、
Roleの無効化時にAuthorization Ruleに従って処理する。

---

# ProductionDelegate Status

ProductionDelegateにもStatusを持たせる。

ACTIVE
INACTIVE

ACTIVEの場合、
DelegateRoleによる権限を有効とする。

INACTIVEの場合、
ProductionDelegateは保持されるが、
委任権限を無効とする。

---

# Permission Changes

DelegateRoleのPermission Setを変更すると、
そのRoleを使用しているProductionDelegateの
権限にも影響する。

例えば、

REHEARSAL_MANAGER
    ↓
Rehearsal.Read
Rehearsal.Create

に、

Rehearsal.Update

を追加した場合、
REHEARSAL_MANAGERを使用する
すべてのProductionDelegateに
Rehearsal.Updateが付与される。

そのため、
DelegateRoleの変更はMaster Dataの変更として扱う。

---

# Authorization Cache

Authorization結果をキャッシュする場合、
以下の変更時には関連Cacheを無効化する。

- ProductionPrimaryManagerChanged
- ProductionDelegateAdded
- ProductionDelegateUpdated
- ProductionDelegateRemoved
- DelegateRoleUpdated
- DelegateRoleActivated
- DelegateRoleDeactivated

Cacheを利用する場合でも、
最終的なAuthorizationの正当性は
Domain RuleとAuthorization Ruleに従う。

---

# Audit

管理権限を利用した操作について、
誰が操作したかを記録できるようにする。

基本的な監査情報として、

CreatedBy
CreatedAt
UpdatedBy
UpdatedAt

を利用する。

Business Resourceの変更では、
実際に操作を実行した利用者をUpdatedByとして記録する。

PrimaryManagerが変更された場合でも、
過去の操作のCreatedByおよびUpdatedByを書き換えない。

ProductionDelegateの変更についても、
同様に過去の監査情報を変更しない。

---

# Authorization Failure

Authorizationに失敗した場合、
基本的には403 Forbiddenを返す。

未認証の場合は401 Unauthorizedを返す。

Resourceが存在しない場合は404 Not Foundを返す。

Business Ruleによる操作拒否と
Authorizationによる操作拒否は区別する。

---

# Security Principle

Authorizationは、
API Endpointだけで実装しない。

API Layer
    ↓
Application Layer
    ↓
Authorization
    ↓
Domain Rule

という構造で、
Business Operationの実行時にも
権限を確認する。

API Endpointを直接呼び出す以外の経路からも、
Authorization Ruleを回避できない設計とする。

---

# Domain Rule and Authorization

Authorizationは、
「誰が操作できるか」を判定する。

Domain Ruleは、
「その操作自体がBusiness上可能か」を判定する。

例えば、

Authorization
    ↓
Reservation.Updateを実行可能

Domain Rule
    ↓
Check In済みReservationは変更不可

というように、
AuthorizationとBusiness Ruleを分離する。

Authorizationを持っていても、
Domain Ruleに違反する操作は実行できない。

---

# Check In Example

例えば、

Person A
    ↓
ProductionDelegate
    ↓
RESERVATION_MANAGER
    ↓
Reservation.CheckIn

というPermissionを持っている場合、
Person AはそのProductionのReservationについて
Check Inを実行できる。

ただし、

Reservation
    ↓
CHECKED_IN

の場合は、
Authorizationがあっても再Check Inはできない。

これはAuthorizationではなく、
Reservation DomainのBusiness Ruleによって拒否する。

---

# Update Example

Reservationの人数を変更する場合、

Person A
    ↓
ProductionDelegate
    ↓
RESERVATION_MANAGER
    ↓
Reservation.Update

によってAuthorizationを通過する。

その後、

Reservation Domain
    ↓
Check In済みか？

を確認する。

CHECKED_INの場合は変更不可とする。

Check In前であれば、
人数変更および必要なReservationSeatの変更を
Business Ruleに従って実行する。

---

# Permission Naming Principles

Permissionは、
Business ResourceとBusiness Operationが
明確になる名前を使用する。

基本形式

Resource.Action

例）

Production.Read
Production.Update

Reservation.Read
Reservation.Update
Reservation.Cancel
Reservation.CheckIn

Rehearsal.Read
Rehearsal.Update

Actionは、
単なる画面操作ではなく、
Business Operationを表す。

---

# Design Principles

- AuthenticationとAuthorizationを分離する。
- Organization ScopeとProduction Scopeを分離する。
- Organization MembershipはOrganization単位の権限を表す。
- PrimaryManagerはProduction単位の全権限を持つ。
- PrimaryManagerはDelegateRoleによる制限を受けない。
- ProductionDelegateはProduction単位の委任権限を表す。
- ProductionDelegateはDelegateRoleを介して権限を取得する。
- DelegateRoleはPermission Setを定義するMaster Dataである。
- DelegateRoleをPersonへ直接付与しない。
- 同一PersonがProductionごとに異なるDelegateRoleを持つことを許可する。
- ProductionDelegateの権限は所属Productionに対してのみ有効とする。
- PermissionはResource.Action形式を基本とする。
- CRUDだけで表現できないBusiness Operationには専用Actionを定義する。
- AuthorizationとDomain Business Ruleを分離する。
- Authorizationを通過してもDomain Ruleに違反する操作は実行できない。
- Check InはReservation単位のBusiness Operationとして扱う。
- Check InではProductionおよびPerformanceのScopeを確認する。
- Check In済みReservationはDomain Ruleによって変更不可とする。
- Productionに紐づく関連ResourceはProduction Scopeを継承して認可する。
- ProductionDelegateの権限変更は監査可能とする。
- 過去のCreatedByおよびUpdatedByは権限変更によって書き換えない。
- AuthorizationはAPI Endpointだけに依存しない。
- Business RuleはDomain Layerが管理する。
