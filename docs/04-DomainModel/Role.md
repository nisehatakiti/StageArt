# StageArt Blueprint

# Domain Model : Role

Version : 1.0

---

# Purpose

Roleは、
PersonがOrganizationにおいて持つ管理・運営上の権限を表すDomainである。

RoleはProductionへの参加区分とは異なる。

OrganizationにおけるRoleは、

「その団体で何を管理できるか」

を表す。

ProductionにおけるParticipant Typeは、

「その公演にどう関わっているか」

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

Person自身にRoleを持たせない。

---

# Role Types

StageArtのOrganization Roleは、
基本的に以下のRoleを提供する。

- 管理者
- 稽古管理者
- 会計管理者

---

# Administrator

管理者。

Organizationにおけるすべての権限を持つ。

管理者は、

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

を利用できる。

管理者は、
自身のOrganizationにおける全権限を付与された状態として扱う。

個別にすべてのPermissionを設定する必要はない。

---

# Rehearsal Manager

稽古管理者。

稽古および稽古関連情報を管理する権限を持つ。

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

---

# Accounting Manager

会計管理者。

会計・予算・予実に関する権限を持つ。

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

---

# Multiple Roles

一人のPersonに複数のRoleを付与できる。

例：

Person A
  ├── 管理者
  ├── 稽古管理者
  └── 会計管理者

ただし、

管理者

を持つ場合、
管理者がOrganizationの全権限を持つため、
他のRoleを追加する必要はない。

Roleの組み合わせによって、
Organization内で利用可能な機能を決定する。

---

# Role and Membership

RoleはMembershipを通じてPersonへ付与される。

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

全権限

Organization Bを利用している場合：

会計関連権限

のみが適用される。

---

# Role and Participant Type

RoleとParticipant Typeは別の概念として扱う。

## Role

Organizationにおける管理・運営上の権限。

Membership
  ↓
Role

## Participant Type

Productionへの参加区分。

Production
  ↓
Participant
  ↓
Participant Type

Participant Typeには、

- キャスト
- スタッフ

などを設定する。

---

# Example

一人のPersonが、

Organization
  劇団A
    Role = 管理者

であり、

Production
  公演A
    Participant Type = キャスト

であることができる。

また、

Organization
  劇団B
    Role = 稽古管理者

であり、

Production
  公演B
    Participant Type = スタッフ

であることもできる。

つまり、

Organization Role

と

Production Participant Type

は独立して管理する。

---

# Information Sharing

情報共有の対象者を指定する際には、
Organization RoleとParticipant Typeを
必要に応じて参照できる。

例：

Announcement
  対象Role
    → 稽古管理者

  対象Participant Type
    → キャスト

この場合、

- Organizationの稽古管理者
- Productionのキャスト

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

# Permission Model

StageArtでは、
Roleを権限のまとまりとして扱う。

基本的には、

Role
  ↓
Permission Set

という考え方を採用する。

管理者については、
個別Permissionの組み合わせではなく、

Administrator
  ↓
All Organization Permissions

として扱う。

稽古管理者は、

Rehearsal Management Permissions

を持つ。

会計管理者は、

Accounting Management Permissions

を持つ。

---

# Permission Scope

Roleによって付与される権限のScopeは
Organization内に限定される。

異なるOrganizationの情報へアクセスすることはできない。

Person
  ↓
Membership
  ↓
Organization A
  ↓
Role

で得られる権限は、
Organization AのScopeにのみ適用される。

---

# Production Delegate

Production単位での権限付与は、
Roleとは別にProductionDelegateで管理する。

例えば、
OrganizationのRoleを持たないPersonへ、

Production A
  ↓
Production Delegate
  ↓
Production Management Permission

のようにProduction単位の権限を委任できる。

Production Delegateは、
Organization全体のRoleを変更しない。

---

# Role Assignment

Roleの付与・変更は、
Organizationの管理権限を持つPersonが行う。

Role変更はMembershipに対する変更として扱う。

Role変更前の情報についても、
必要に応じて監査情報を保持できる。

---

# Role Lifecycle

Role自体は固定されたRole Definitionとして管理する。

PersonへのRole付与状態はMembership側で管理する。

Role Definitionそのものを
Personごとに複製しない。

---

# Business Rules

- RoleはOrganization Contextに属する。
- RoleはPersonの属性ではない。
- RoleはMembershipを通じてPersonへ付与される。
- 一人のPersonは複数Organizationで異なるRoleを持てる。
- 管理者はOrganizationの全権限を持つ。
- 稽古管理者は稽古関連機能を管理できる。
- 会計管理者は会計・予算・予実関連機能を管理できる。
- 一人のPersonに複数Roleを付与できる。
- 管理者を持つPersonには、他のRoleを追加する必要はない。
- RoleはProductionへの参加区分を表さない。
- Productionへの参加区分はParticipant Typeで管理する。
- キャストとスタッフはParticipant Typeとして管理する。
- RoleとParticipant Typeは独立して管理する。
- 情報共有ではRoleとParticipant Typeを組み合わせて対象者を指定できる。
- Roleによる権限はOrganization Scopeに限定される。
- Production単位の権限はProductionDelegateで管理する。
- Role変更はMembershipに対する変更として扱う。

---

# Domain Events

Roleに関連する主なDomain Event：

- RoleAssigned
- RoleChanged
- RoleRemoved

Role Definitionそのものを変更する場合のEventは、
Organizationの管理ルールに従う。

---

# Design Decisions

StageArtでは、
Roleを複雑な階層構造にしない。

基本Roleは、

- 管理者
- 稽古管理者
- 会計管理者

とする。

管理者はOrganizationの全権限を持つ。

稽古管理者と会計管理者は、
それぞれの業務領域のみを管理する。

必要に応じて複数Roleを付与できる。

RoleとParticipant Typeは明確に分離する。

RoleはOrganizationでの権限。

Participant TypeはProductionでの参加区分。

この分離により、

Organization Role
  管理者

Production Participant Type
  キャスト

のような組み合わせを自然に表現できる。

情報共有では、
RoleとParticipant Typeの両方を対象条件として利用できる。

---

# Future

将来的に必要となった場合、

- より細かなPermission
- Roleのカスタマイズ
- Organization独自Role
- Permission Override

などへ拡張できる構造とする。

ただし、
初期実装ではRoleを複雑化しない。

---

# Design Principles

- RoleはOrganization Contextに属する。
- RoleはPersonの属性ではない。
- RoleはMembershipを通じて付与する。
- 管理者はOrganizationの全権限を持つ。
- 稽古管理者は稽古関連機能を管理する。
- 会計管理者は会計・予算・予実関連機能を管理する。
- 複数Roleを付与できる。
- RoleとParticipant Typeを分離する。
- キャストとスタッフはParticipant Typeで表現する。
- RoleはOrganization Scopeに限定する。
- Production単位の権限はProductionDelegateで管理する。
- 情報共有ではRoleとParticipant Typeを参照できる。
- 初期実装ではRoleを必要以上に複雑化しない。
- Blueprintを唯一の設計基準とする。
