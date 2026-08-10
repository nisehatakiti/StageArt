# StageArt Blueprint
# Domain Model : DelegateRole

Version : 1.0

---

# Purpose

DelegateRoleは、
ProductionDelegateへ付与する権限セットを定義するMaster Domainである。

DelegateRoleは、
Production単位でPersonへ委任する管理権限を定義する。

DelegateRole自身はPersonやProductionを直接管理しない。

ProductionDelegateを介して、
特定のProductionにおけるPersonの権限を決定する。

---

# Concept

ProductionにはPrimaryManagerとProductionDelegateが存在する。

PrimaryManagerはProductionに関する全権限を持つ。

ProductionDelegateは、
DelegateRoleによって定義された権限のみを持つ。

関係は以下の通り。

Production
    │
    └── ProductionDelegate
            ├── Person
            └── DelegateRole
                    └── Permission Set

DelegateRole
    ↓
「どのような管理権限を委任するか」

ProductionDelegate
    ↓
「誰にその権限を委任するか」

という責務分離を行う。

---

# Responsibility

DelegateRoleは以下を管理する。

- DelegateRoleId
- Code
- Name
- Description
- Status
- Permission Set
- CreatedAt
- CreatedBy
- UpdatedAt
- UpdatedBy

DelegateRoleは、
ProductionDelegateへ付与する権限セットを定義する。

---

# Identity

DelegateRoleはDelegateRoleIdによって識別する。

Codeはシステム上の固定識別子として使用する。

例）

- REHEARSAL_MANAGER
- RESERVATION_MANAGER
- PARTICIPANT_MANAGER
- PERFORMANCE_MANAGER

Codeは一意である。

---

# Master Data

DelegateRoleはシステムが管理するMaster Dataである。

ProductionごとにDelegateRoleを作成しない。

ProductionDelegateは、
あらかじめ定義されたDelegateRoleを参照する。

例えば、

Production A
    ↓
ProductionDelegate
    ↓
REHEARSAL_MANAGER

Production B
    ↓
ProductionDelegate
    ↓
REHEARSAL_MANAGER

という形で、
同じDelegateRoleを複数のProductionで利用できる。

---

# Production Scope

DelegateRoleによって定義される権限は、
ProductionDelegateを介してProduction単位で適用される。

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

という状態を許可する。

同じPersonであっても、
Productionごとに異なるDelegateRoleを持つことができる。

---

# Permission Set

DelegateRoleは複数のPermissionを持つ。

Permissionは、
システム上で実行可能な操作を表す。

例えば、

REHEARSAL_MANAGER

    ↓
Rehearsal.Read
Rehearsal.Create
Rehearsal.Update
Rehearsal.Delete

RESERVATION_MANAGER

    ↓
Reservation.Read
Reservation.Create
Reservation.Update
Reservation.Cancel

という形で定義する。

DelegateRoleは、
個々の利用者へ直接Permissionを付与するのではなく、
Permission Setとしてまとめて管理する。

---

# Permission Naming

Permissionは、

Resource.Action

という形式を基本とする。

例）

Production.Read
Production.Update

Performance.Read
Performance.Create
Performance.Update

Participant.Read
Participant.Create
Participant.Update
Participant.Delete

Reservation.Read
Reservation.Create
Reservation.Update
Reservation.Cancel

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

具体的なPermission一覧は、
Authorization設計で定義する。

---

# Example Roles

初期段階では、
以下のDelegateRoleを想定する。

## REHEARSAL_MANAGER

稽古管理を担当するためのRole。

想定する権限

- Rehearsal.Read
- Rehearsal.Create
- Rehearsal.Update
- Rehearsal.Delete
- Schedule.Read

---

## RESERVATION_MANAGER

予約管理を担当するためのRole。

想定する権限

- Reservation.Read
- Reservation.Create
- Reservation.Update
- Reservation.Cancel
- Performance.Read

---

## PARTICIPANT_MANAGER

Participant管理を担当するためのRole。

想定する権限

- Participant.Read
- Participant.Create
- Participant.Update
- Participant.Delete

---

## PERFORMANCE_MANAGER

Performance管理を担当するためのRole。

想定する権限

- Performance.Read
- Performance.Create
- Performance.Update
- Performance.Delete

---

# Role Composition

一人のPersonが複数のDelegateRoleを必要とする場合、
ProductionDelegateで複数のRoleを付与できる設計へ
将来的に拡張できる。

現Versionでは、
一つのProductionDelegateに対して
一つのDelegateRoleを設定する。

複数Roleの付与が必要になった場合は、

ProductionDelegate
    ├── DelegateRole
    ├── DelegateRole
    └── DelegateRole

という構造へ拡張可能とする。

ただし、
現時点では複数Roleを前提とした実装は行わない。

---

# Primary Manager

PrimaryManagerはDelegateRoleを使用しない。

PrimaryManagerはProductionに関する全権限を持つ。

したがって、

PrimaryManager
    ↓
All Permissions

ProductionDelegate
    ↓
DelegateRole
    ↓
Permission Set

という二つの権限経路を持つ。

PrimaryManagerにDelegateRoleを設定して
権限を制限することはしない。

---

# Role Status

DelegateRoleはStatusを持つ。

例）

- ACTIVE
- INACTIVE

ACTIVEのDelegateRoleのみ、
新しいProductionDelegateへ設定できる。

INACTIVEのDelegateRoleは、
新規設定できない。

既存のProductionDelegateが
INACTIVEとなったDelegateRoleを参照している場合の扱いは、
Authorization設計で定義する。

---

# Role Changes

DelegateRoleのPermission Setを変更すると、
そのDelegateRoleを使用している
ProductionDelegateの権限にも影響する。

例えば、

REHEARSAL_MANAGER

に新しいPermissionを追加した場合、

REHEARSAL_MANAGERを使用している
すべてのProductionDelegateに
そのPermissionが付与される。

そのため、
DelegateRoleの変更はMaster Dataの変更として管理する。

---

# Role Assignment

DelegateRoleをPersonへ直接設定しない。

正しい関係は、

Person
    ↑
ProductionDelegate
    ↑
DelegateRole

である。

これにより、

同じPersonでもProductionごとに
異なるRoleを持つことができる。

---

# Authorization

Authorizationは以下の優先関係で判定する。

PrimaryManager
    ↓
全権限

ProductionDelegate
    ↓
DelegateRole
    ↓
Permission Set

権限なし
    ↓
操作不可

PrimaryManagerはDelegateRoleによる制限を受けない。

ProductionDelegateは、
DelegateRoleに定義されたPermissionのみ実行できる。

---

# Organization Membership

DelegateRoleはOrganization Membershipとは独立して管理する。

Organization Membershipは、
Organizationに対する所属および権限を表す。

DelegateRoleは、
Productionに対する委任権限を表す。

例えば、

Person A
    ↓
Organization Membership
    ↓
Organization A

Person A
    ↓
ProductionDelegate
    ↓
Production A
    ↓
REHEARSAL_MANAGER

という関係を持つことができる。

Organization Membershipによって
ProductionDelegateのRoleが自動的に決定されることはない。

---

# Participant

DelegateRoleはParticipantとは関係しない。

ParticipantはProductionへの参加を表す。

DelegateRoleはProductionに対する管理権限を表す。

同じPersonが、

Participant
    ↓
CAST

ProductionDelegate
    ↓
REHEARSAL_MANAGER

という状態になることを許可する。

Participantであることによって、
自動的にDelegateRoleが付与されることはない。

---

# Audit Information

DelegateRoleはMaster Dataであるため、
変更時の監査情報を保持する。

CreatedBy

DelegateRoleを作成した主体。

CreatedAt

DelegateRoleを作成した日時。

UpdatedBy

DelegateRoleを最後に変更した主体。

UpdatedAt

DelegateRoleを最後に変更した日時。

---

# Domain Events

DelegateRoleの変更によって、
Authorizationの状態が変化する。

代表的なDomain Eventとして、

- DelegateRoleCreated
- DelegateRoleUpdated
- DelegateRoleActivated
- DelegateRoleDeactivated

を定義できる。

ただし、
Role変更Eventの具体的な利用方法は
Authorization設計で決定する。

---

# History

DelegateRoleの作成・変更・有効化・無効化は、
Personの活動Historyを生成しない。

DelegateRoleは人物の活動実績ではなく、
権限設定を表すMaster Dataである。

---

# Business Rules

- DelegateRoleはMaster Dataである。
- DelegateRoleはProductionDelegateへ付与する権限セットを定義する。
- DelegateRoleはPersonへ直接付与しない。
- DelegateRoleはProductionごとに作成しない。
- DelegateRoleは複数のProductionで利用できる。
- DelegateRoleのCodeは一意である。
- DelegateRoleによる権限はProduction単位で適用される。
- ProductionDelegateはDelegateRoleを参照する。
- ProductionDelegateはDelegateRoleに定義されたPermissionのみ持つ。
- PrimaryManagerはDelegateRoleを使用しない。
- PrimaryManagerはProductionに関する全権限を持つ。
- Organization MembershipとDelegateRoleは別の権限体系である。
- ParticipantとDelegateRoleは別の概念である。
- ACTIVEのDelegateRoleのみ新規設定できる。
- DelegateRoleのPermission Set変更は、そのRoleを使用するProductionDelegateへ影響する。
- DelegateRoleの変更はMaster Dataの変更として管理する。
- DelegateRoleはPersonの活動Historyを生成しない。

---

# Design Decisions

DelegateRoleはProduction単位の委任権限を
定義するMaster Dataである。

ProductionDelegateは、
PersonとDelegateRoleをProductionへ紐付ける。

PrimaryManagerはDelegateRoleによる制限を受けず、
Productionに関する全権限を持つ。

ProductionDelegateは、
DelegateRoleに定義された権限のみを持つ。

同一PersonがProductionごとに
異なるDelegateRoleを持つことを許可する。

DelegateRoleはPerson自身のRoleではない。

DelegateRoleはOrganization Membershipとは独立している。

DelegateRoleはParticipantとは独立している。

---

# Future

将来的に以下へ対応する。

- DelegateRoleの管理画面
- Permissionの管理画面
- RoleとPermissionの詳細な組み合わせ
- 複数DelegateRoleの付与
- Roleの継承
- Roleの有効期間
- Productionごとの権限カスタマイズ
- Rehearsal Management
- Schedule Management
- Reservation Management
- Participant Management
- Performance Management
- Document Management

将来的な権限体系の拡張においても、
ProductionDelegateをPersonとDelegateRoleの
Production単位の紐付けとして維持する。

---

# Design Principles

- DelegateRoleは権限セットを定義するMaster Dataである。
- DelegateRoleはPersonへ直接付与しない。
- DelegateRoleはProductionDelegateを介して適用する。
- DelegateRoleはProduction単位で適用される。
- 同一PersonがProductionごとに異なるDelegateRoleを持つことを許可する。
- DelegateRoleはOrganization Membershipとは独立する。
- DelegateRoleはParticipantとは独立する。
- PrimaryManagerはDelegateRoleを使用しない。
- PrimaryManagerはProductionに関する全権限を持つ。
- ProductionDelegateはDelegateRoleに定義された権限のみ持つ。
- DelegateRoleのPermission SetはMaster Dataとして管理する。
- DelegateRoleの変更は、それを使用するProductionDelegateへ影響する。
- DelegateRoleの権限はProduction単位で有効となる。
- Business RuleはDomain Layerが管理する。
- APIはApplication Layerの公開インターフェースとして機能する。