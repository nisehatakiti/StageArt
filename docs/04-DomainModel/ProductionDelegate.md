# StageArt Blueprint

# Domain Model : ProductionDelegate

Version : 2.1

---

# Purpose

ProductionDelegateは、
特定のProductionに対してRoleを適用されたPersonを表すDomainである。

ProductionDelegateは、
Productionの管理業務を特定のPersonへ委任するために使用する。

ProductionDelegate自身は権限の内容を定義しない。

付与される権限はRoleによって定義される。

RoleはRole Domainで一元管理する。

ProductionDelegateは、
Production ScopeにおいてPersonへRoleを適用する関係として扱う。

---

# Concept

Productionには一人のPrimaryManagerが存在する。

PrimaryManagerはProductionに関する全権限を持つ。

PrimaryManagerは必要に応じて、
Productionの管理業務を他のPersonへ委任できる。

その委任関係をProductionDelegateとして管理する。

基本構造：

Production
    │
    ├── PrimaryManager
    │       └── Person
    │
    └── ProductionDelegate
            ├── Person
            └── Role

ProductionDelegateは、
Role Definitionそのものではない。

Roleは、
「何ができるか」を定義する。

ProductionDelegateは、
「誰に、どのProductionについて、そのRoleを与えたか」
を表す。

---

# Responsibility

ProductionDelegateは以下を管理する。

- Production
- Person
- Role
- Status
- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

ProductionDelegateは、
Production単位でPersonにRoleを適用する関係を表す。

ProductionDelegateは、
Permissionそのものを直接保持しない。

PermissionはRoleを通じて決定する。

---

# Identity

ProductionDelegateはProductionDelegateIdによって識別する。

ProductionDelegateIdは変更できない。

ProductionDelegateは、

- Production
- Person
- Role

の組み合わせによって、
一つのProduction ScopeにおけるRoleの適用関係を表す。

---

# Production

ProductionDelegateは必ず一つのProductionに所属する。

ProductionDelegateはProductionから独立して存在できない。

ProductionDelegateの追加・変更・削除は、
Productionを経由して管理する。

ProductionDelegateによって適用されたRoleの権限は、
所属するProductionに対してのみ有効である。

---

# Person

ProductionDelegateは必ず一人のPersonを参照する。

PersonはProductionDelegateそのものの所有者ではない。

同一Personを複数のProductionに
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

Person自身にProduction ScopeのRoleを設定することはしない。

---

# Role

ProductionDelegateは一つのRoleを参照する。

Roleは、
Personに適用される権限セットを定義するMasterである。

RoleはProductionDelegate専用ではない。

同じRole Definitionを、

- Organization Scope
- Production Scope

の両方で利用できる。

例えば、

ProductionDelegate
    ↓
Role = Rehearsal Manager

の場合、

Rehearsal Managerに定義されたPermissionが、
そのProductionDelegateへ適用される。

Roleの具体的な定義はRole Domainで管理する。

---

# Role Application

ProductionDelegateは、
Production ScopeにおいてPersonへRoleを適用する関係である。

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
Roleそのものを作成しない。

既存のRole Definitionを、
特定Productionに対してPersonへ適用する。

---

# Role Scope

Role DefinitionそのものはScopeを持たない。

ProductionDelegateによって適用されたRoleは、
Production Scopeで有効になる。

例えば、

Production A
    ↓
ProductionDelegate
    ├── Person A
    └── Role = Rehearsal Manager

の場合、

Person AはProduction Aについて
Rehearsal ManagerのPermissionを持つ。

Person AがProduction Bについて
同じPermissionを持つとは限らない。

---

# Organization Role Relationship

ProductionDelegateによるRoleの適用と、
OrganizationのMembershipによるRoleの適用は、
異なるScopeとして管理する。

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
Organization ScopeとProduction Scopeの
両方で利用できる。

---

# Membership Independence

ProductionDelegateとMembershipは、
異なる概念である。

Membershipは、
PersonがOrganizationに所属していることを表す。

ProductionDelegateは、
Personが特定のProductionに対して
Roleを適用されていることを表す。

例えば、

Person A
    ↓
Membership
    ↓
Organization A

Person A
    ↓
ProductionDelegate
    ↓
Production A
    ↓
Role = Rehearsal Manager

という関係を持つことができる。

ProductionDelegateによるRoleの適用は、
PersonのOrganization Membershipを変更しない。

ProductionDelegateの存在によって、
Organization ScopeのRoleが自動的に変更されることもない。

---

# Organization Membership Requirement

ProductionDelegateへの登録に
Organization Membershipを必須とするかどうかは、
OrganizationおよびAuthorizationのBusiness Ruleに従う。

ProductionDelegate Domain自身は、
Membershipの有無をRole Definitionの条件として扱わない。

ProductionDelegateは、
PersonにProduction ScopeのRoleを適用する
Domainとして扱う。

---

# Primary Manager Relationship

PrimaryManagerはProductionに一人だけ存在する。

PrimaryManagerはProductionに関する全権限を持つ。

ProductionDelegateは、
PrimaryManagerから委任されたRoleのPermissionのみを持つ。

関係は以下の通り。

PrimaryManager
    ↓
Production
    ↓
ProductionDelegate
    ↓
Role
    ↓
Permission

ProductionDelegateは、
PrimaryManagerと同等の権限を自動的には持たない。

---

# Authorization

ProductionDelegateの権限は、
適用されたRoleによって決定する。

Roleに定義されていない操作は実行できない。

PrimaryManagerは、
Productionの全権限を持つ。

ProductionDelegateは、
適用されたRoleに含まれるPermissionのみを持つ。

ProductionDelegateのPermissionは、
Production Scopeに限定される。

---

# Permission Scope

ProductionDelegateのPermissionは、
Roleが適用されたProductionに対してのみ有効である。

例えば、

Person A

Production A
    ↓
ProductionDelegate
    └── Role = Rehearsal Manager

Production B
    ↓
Roleの適用なし

という状態の場合、

Person AはProduction Aについて
Rehearsal ManagerのPermissionを持つが、
Production Bについて同じPermissionを持たない。

---

# Multiple Roles

同一Personに対して、
同一Production内で複数のRoleを適用できる。

例えば、

Production A
    ├── ProductionDelegate
    │       ├── Person A
    │       └── Role = Rehearsal Manager
    │
    ├── ProductionDelegate
    │       ├── Person A
    │       └── Role = Reservation Manager
    │
    └── ProductionDelegate
            ├── Person A
            └── Role = Participant Manager

という状態を許可する。

これにより、
一人のPersonが複数の管理領域を担当できる。

---

# Role Change

ProductionDelegateに適用されているRoleは、
必要に応じて変更できる。

例えば、

ProductionDelegate
    ├── Person A
    └── Role = Rehearsal Manager

を、

ProductionDelegate
    ├── Person A
    └── Role = Reservation Manager

へ変更できる。

Roleを変更すると、
そのPersonにProduction Scopeで適用されるPermissionも変更される。

Role Definitionそのものが変更されるわけではない。

変更されるのは、
ProductionDelegateに適用するRoleである。

---

# Status

ProductionDelegateは状態を持つ。

例：

- ACTIVE
- INACTIVE

ACTIVEの場合、
適用されたRoleのPermissionを有効とする。

INACTIVEの場合、
ProductionDelegateは保持されるが、
Productionに対するRoleのPermissionは無効とする。

Role Definition自体の状態とは別に管理する。

---

# Create

ProductionDelegateは、
Productionに対する管理権限を持つ利用者によって作成される。

通常はPrimaryManagerが作成する。

作成時にRoleを指定する。

作成時に以下を確定する。

- Production
- Person
- Role
- Status
- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

初期状態は、

Status
    = ACTIVE

とする。

UpdatedByにはCreatedByを設定する。

UpdatedAtにはCreatedAtを設定する。

---

# Update

ProductionDelegateは、
Productionに対する管理権限を持つ利用者によって変更できる。

変更可能な情報：

- Person
- Role
- Status

ProductionDelegateIdは変更できない。

Roleを変更すると、
そのPersonにProduction Scopeで適用されるPermissionも変更される。

StatusをINACTIVEに変更すると、
ProductionDelegateによるRoleの適用は無効になる。

変更時には、

- UpdatedBy
- UpdatedAt

を更新する。

---

# Remove

ProductionDelegateは、
Productionから削除できる。

削除はProductionを経由して行う。

削除されたProductionDelegateは、
そのProductionに対するRoleの適用を失う。

過去に実行された操作のCreatedByおよびUpdatedByは
変更しない。

ProductionDelegateRemovedを発行する。

---

# Relationship with Participant

ProductionDelegateとParticipantは異なる概念である。

Participantは、
PersonまたはOrganizationがProductionへ参加していることを表す。

ProductionDelegateは、
PersonがProduction ScopeでRoleを適用されていることを表す。

同一Personが、

- Participant
- ProductionDelegate

の両方になることを許可する。

例えば、

Person A
    │
    ├── Participant
    │       └── ParticipantType = CAST
    │
    └── ProductionDelegate
            └── Role = Rehearsal Manager

という状態を許可する。

Participantであることによって、
自動的にProductionDelegateになることはない。

ProductionDelegateであることによって、
自動的にParticipantになることもない。

---

# Participant Type

Participant Typeは、
Productionにおける参加区分を表す。

例：

- CAST
- STAFF

Participant TypeはRoleではない。

Participant Typeによって、
管理権限を自動的に付与してはならない。

Roleによって、
Participant Typeを自動的に決定してはならない。

---

# Example : Cast and Delegate

Person AがProduction Aに出演する場合：

Person A
    ↓
Participant
    ↓
Production A
    ↓
ParticipantType = CAST

Person AがさらにProduction Aの
稽古管理を担当する場合：

Person A
    ↓
ProductionDelegate
    ↓
Production A
    ↓
Role = Rehearsal Manager

となる。

この二つの関係は、
それぞれ独立して管理する。

---

# Audit Information

ProductionDelegateは監査情報を保持する。

CreatedBy

ProductionDelegateを作成した利用者を表す。

CreatedAt

ProductionDelegateが作成された日時を表す。

UpdatedBy

ProductionDelegateを最後に変更した利用者を表す。

UpdatedAt

ProductionDelegateが最後に変更された日時を表す。

これらの情報は、
Production ScopeにおけるRoleの適用状態の
管理履歴を確認するために利用する。

---

# Domain Events

ProductionDelegateは以下のDomain Eventを発行する。

- ProductionDelegateAdded
- ProductionDelegateUpdated
- ProductionDelegateRemoved

Domain Eventには、
Business Processに必要な情報を含める。

例：

- ProductionDelegateId
- ProductionId
- PersonId
- RoleId

旧DelegateRoleIdは使用しない。

---

# History

ProductionDelegateの追加・変更・削除は、
Personの活動Historyを生成しない。

ProductionDelegateは、
Personの公演参加実績を表すものではない。

ProductionDelegateは、
Productionに対するRoleの適用関係を表す。

したがって、

- ProductionDelegateAdded
- ProductionDelegateUpdated
- ProductionDelegateRemoved

によって、

- Participation History
- Audience History

などを生成しない。

---

# Business Rules

- ProductionDelegateはProductionの子Entityである。
- ProductionDelegateは必ず一つのProductionに所属する。
- ProductionDelegateは必ず一人のPersonを参照する。
- ProductionDelegateは一つのRoleを参照する。
- ProductionDelegateは権限そのものを保持しない。
- RoleがPermission Setを定義する。
- Role DefinitionはRole Domainで一元管理する。
- ProductionDelegateはProduction ScopeにおいてPersonへRoleを適用する。
- Role DefinitionはProductionDelegate専用ではない。
- 同じRole DefinitionをOrganization ScopeとProduction Scopeで利用できる。
- ProductionDelegateによるRoleのPermissionは所属Productionに対してのみ有効である。
- 同一Personを複数ProductionのProductionDelegateとして登録できる。
- 同一PersonがProductionごとに異なるRoleを適用されることを許可する。
- 同一Personに同一Production内で複数のRoleを適用できる。
- Person自身にProduction ScopeのRoleを設定しない。
- ProductionDelegateによるRoleの適用はMembershipを変更しない。
- ProductionDelegateによるRoleの適用はOrganization ScopeのRoleを変更しない。
- PrimaryManagerはProductionに一人だけ存在する。
- PrimaryManagerはProductionに関する全権限を持つ。
- ProductionDelegateはPrimaryManagerと同等の権限を自動的には持たない。
- ProductionDelegateは適用されたRoleに含まれるPermissionのみ持つ。
- ACTIVEのProductionDelegateのみRoleのPermissionを有効とする。
- INACTIVEのProductionDelegateはRoleのPermissionを持たない。
- ParticipantとProductionDelegateは異なる概念である。
- ParticipantであることによってProductionDelegateにはならない。
- ProductionDelegateであることによってParticipantにはならない。
- Participant TypeはRoleではない。
- CAST / STAFFはParticipant TypeでありRoleではない。
- ProductionDelegateはProductionを経由して管理する。
- ProductionDelegateの作成・変更・削除は適切なAuthorizationを必要とする。
- ProductionDelegateの変更時にUpdatedByおよびUpdatedAtを更新する。
- ProductionDelegateの作成時にCreatedByおよびCreatedAtを記録する。
- ProductionDelegateの削除後も過去の監査情報は変更しない。
- ProductionDelegateの追加・変更・削除はPersonのParticipation Historyを生成しない。
- DelegateRoleという別のRole Definitionは使用しない。

---

# Design Decisions

ProductionDelegateは、
Productionに対する管理権限の委任を表す。

ProductionDelegateはProductionの子Entityとして管理する。

ProductionDelegateは、
Production ScopeにおいてPersonへRoleを適用する関係である。

Roleは、
「何ができるか」を定義する。

ProductionDelegateは、
「誰に、どのProductionについて、そのRoleを与えたか」
を表す。

Role Definitionは、
Role Domainで一元管理する。

DelegateRoleというProduction専用のRole体系は廃止する。

Organization ScopeとProduction Scopeでは、
同じRole Definitionを利用する。

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

という構造を使用する。

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

という構造を使用する。

PrimaryManagerはProductionに一人だけ存在する。

PrimaryManagerはProductionに関する全権限を持つ。

ProductionDelegateは、
PrimaryManagerから必要な管理業務を委任されたPersonを表す。

ProductionDelegateは、
Organization Membershipとは別のScopeで管理する。

ProductionDelegateは、
Participantとは独立して管理する。

Participant Typeは、
Productionへの参加区分を表す。

Roleは、
管理・運営上の権限を表す。

この二つを統合しない。

---

# Future

将来的に以下へ対応できる。

- Roleの追加
- Roleの変更
- Roleの廃止
- Permission Setの細分化
- Production単位の権限管理
- Rehearsal管理権限
- Timetable管理権限
- Reservation管理権限
- Participant管理権限
- Performance管理権限
- Document管理権限
- Role適用履歴
- Role適用の有効期間
- より細かなScope

Roleの具体的な定義はRole Domainで管理する。

ProductionDelegateは、
Role Definitionを増やすためのDomainではなく、
既存RoleをProduction Scopeへ適用するためのDomainとして維持する。

---

# Design Principles

- ProductionDelegateはProduction ScopeにおいてPersonへRoleを適用する関係である。
- ProductionDelegateはProductionの子Entityである。
- ProductionDelegateはPersonを参照する。
- ProductionDelegateはRoleを参照する。
- RoleはPermission Setを定義する。
- Role DefinitionはRole Domainで一元管理する。
- DelegateRoleという別Role Definitionを作成しない。
- Role DefinitionはProductionDelegate専用ではない。
- 同じRole DefinitionをOrganization ScopeとProduction Scopeで利用する。
- ProductionDelegateはRoleそのものではない。
- ProductionDelegateは「誰に、どのProductionで、どのRoleを与えたか」を表す。
- ProductionDelegateのPermissionはProduction Scopeに限定される。
