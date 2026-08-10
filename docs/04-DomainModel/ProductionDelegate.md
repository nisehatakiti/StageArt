# StageArt Blueprint
# Domain Model : ProductionDelegate

Version : 1.0

---

# Purpose

ProductionDelegateは、
Productionに対して管理権限を委任されたPersonを表す子Entityである。

ProductionDelegateは、
ProductionのPrimaryManagerから委任された管理権限を
特定のPersonへ付与するために使用する。

ProductionDelegate自身は権限の内容を定義しない。

付与される権限はDelegateRoleによって定義される。

---

# Concept

Productionには一人のPrimaryManagerが存在する。

PrimaryManagerはProductionに関する全権限を持つ。

PrimaryManagerは必要に応じて、
Productionの管理業務を他のPersonへ委任できる。

その委任関係をProductionDelegateとして管理する。

Production
    │
    ├── PrimaryManager
    │       └── Person
    │
    └── ProductionDelegate
            ├── Person
            └── DelegateRole

---

# Responsibility

ProductionDelegateは以下を管理する。

- Production
- Person
- DelegateRole
- Status
- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

ProductionDelegateは、
Production単位の管理権限の委任関係を表す。

---

# Identity

ProductionDelegateはProductionDelegateIdによって識別する。

ProductionDelegateIdは変更できない。

ProductionDelegateは、
ProductionとPersonとDelegateRoleの組み合わせによって
一つの委任関係を表す。

---

# Production

ProductionDelegateは必ず一つのProductionに所属する。

ProductionDelegateはProductionから独立して存在できない。

ProductionDelegateの追加・変更・削除は、
Productionを経由して管理する。

ProductionDelegateの権限は、
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
Person A
    ↓
ProductionDelegate

Production B
    ↓
Person A
    ↓
ProductionDelegate

という状態を許可する。

---

# Delegate Role

ProductionDelegateは一つのDelegateRoleを参照する。

DelegateRoleは、
ProductionDelegateへ付与される権限セットを表すマスターである。

ProductionDelegateは権限そのものを保持しない。

権限の内容はDelegateRoleによって決定する。

例えば、

ProductionDelegate
    ↓
REHEARSAL_MANAGER

の場合、

REHEARSAL_MANAGERに定義された権限が
そのProductionDelegateへ付与される。

---

# Delegate Role Scope

DelegateRoleによる権限は、
Production単位で適用される。

同一Personであっても、
Productionごとに異なるDelegateRoleを持つことができる。

例えば、

Production A
    ↓
Person A
    ↓
REHEARSAL_MANAGER

Production B
    ↓
Person A
    ↓
RESERVATION_MANAGER

という状態を許可する。

Person自身にDelegateRoleを設定することはしない。

---

# Status

ProductionDelegateは状態を持つ。

例）

- ACTIVE
- INACTIVE

ACTIVEの場合、
DelegateRoleによって定義された権限を有効とする。

INACTIVEの場合、
ProductionDelegateは保持されるが、
Productionに対する委任権限は持たない。

---

# Create

ProductionDelegateは、
Productionに対する管理権限を持つ利用者によって作成される。

通常はPrimaryManagerが作成する。

DelegateRoleを指定して作成する。

作成時に以下を確定する。

- Production
- Person
- DelegateRole
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

変更可能な情報

- Person
- DelegateRole
- Status

ProductionDelegateIdは変更できない。

DelegateRoleを変更すると、
そのPersonに付与されるProduction単位の権限も変更される。

StatusをINACTIVEに変更すると、
委任権限は無効になる。

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
そのProductionに対する委任権限を失う。

過去に実行された操作のCreatedByおよびUpdatedByは
変更しない。

ProductionDelegateRemovedを発行する。

---

# Primary Manager Relationship

PrimaryManagerはProductionに一人だけ存在する。

PrimaryManagerはProductionに関する全権限を持つ。

ProductionDelegateは、
PrimaryManagerから委任された権限のみを持つ。

関係は以下の通り。

PrimaryManager
    ↓
Production
    ↓
ProductionDelegate
    ↓
DelegateRole
    ↓
Permission Set

ProductionDelegateはPrimaryManagerと同等の権限を持たない。

---

# Authorization

ProductionDelegateの権限は、
DelegateRoleによって決定する。

DelegateRoleに定義されていない操作は実行できない。

PrimaryManagerはDelegateRoleによる制限を受けない。

ProductionDelegateは、
Organization Membershipとは別の権限体系として管理する。

Organization Membershipによって
ProductionDelegateのRoleが自動的に決定されることはない。

---

# Permission Scope

ProductionDelegateの権限は、
Productionに対してのみ有効である。

例えば、

Person A

Production A
    ↓
REHEARSAL_MANAGER

Production B
    ↓
権限なし

という状態を許可する。

ProductionDelegateがProduction Aで
管理権限を持っていても、
Production Bを管理できるとは限らない。

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
管理権限の変更履歴を確認するために利用する。

---

# Domain Events

ProductionDelegateは以下のDomain Eventを発行する。

- ProductionDelegateAdded
- ProductionDelegateUpdated
- ProductionDelegateRemoved

Domain Eventには、
ProductionDelegateId、
ProductionId、
PersonId、
DelegateRoleIdなど、
Business Processに必要な情報を含める。

---

# History

ProductionDelegateの追加・変更・削除は、
Personの活動Historyを生成しない。

ProductionDelegateは
Personの公演参加実績を表すものではない。

ProductionDelegateは
Productionに対する管理権限の委任を表す。

したがって、

ProductionDelegateAdded
ProductionDelegateUpdated
ProductionDelegateRemoved

によって、
Participation HistoryやAudience Historyを生成しない。

---

# Relationship with Participant

ProductionDelegateとParticipantは異なる概念である。

Participantは、
PersonまたはOrganizationがProductionへ参加していることを表す。

ProductionDelegateは、
PersonがProductionの管理権限を委任されていることを表す。

同一Personが、

- Participant
- ProductionDelegate

の両方になることを許可する。

例えば、

Person A
    │
    ├── Participant
    │       └── CAST
    │
    └── ProductionDelegate
            └── REHEARSAL_MANAGER

という状態を許可する。

Participantであることによって、
自動的にProductionDelegateになることはない。

ProductionDelegateであることによって、
自動的にParticipantになることもない。

---

# Relationship with Membership

ProductionDelegateとMembershipは異なる概念である。

Membershipは、
PersonがOrganizationに所属していることを表す。

ProductionDelegateは、
Personが特定のProductionに対して
管理権限を持つことを表す。

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

という関係を持つことができる。

Organization Membershipがなくても、
Business Rule上許可される場合は
ProductionDelegateとして登録できる。

---

# Business Rules

- ProductionDelegateはProductionの子Entityである。
- ProductionDelegateは必ず一つのProductionに所属する。
- ProductionDelegateは必ず一人のPersonを参照する。
- ProductionDelegateは一つのDelegateRoleを参照する。
- ProductionDelegateは権限そのものを保持しない。
- DelegateRoleが権限セットを定義する。
- DelegateRoleはProduction単位で適用される。
- 同一Personを複数ProductionのDelegateとして登録できる。
- 同一PersonがProductionごとに異なるDelegateRoleを持つことができる。
- Person自身にDelegateRoleを設定しない。
- ProductionDelegateの権限は所属Productionに対してのみ有効である。
- PrimaryManagerはProductionに一人だけ存在する。
- PrimaryManagerはProductionに関する全権限を持つ。
- PrimaryManagerはDelegateRoleによる制限を受けない。
- ProductionDelegateはDelegateRoleに定義された権限のみ持つ。
- Organization MembershipとProductionDelegateの権限は分離する。
- ParticipantとProductionDelegateは異なる概念である。
- ParticipantであることによってProductionDelegateにはならない。
- ProductionDelegateであることによってParticipantにはならない。
- ACTIVEのProductionDelegateのみ委任権限を有効とする。
- INACTIVEのProductionDelegateは委任権限を持たない。
- ProductionDelegateはProductionを経由して管理する。
- ProductionDelegateの変更時にUpdatedByおよびUpdatedAtを更新する。
- ProductionDelegateの作成時にCreatedByおよびCreatedAtを記録する。
- ProductionDelegateの削除後も過去の監査情報は変更しない。
- ProductionDelegateの追加・変更・削除はHistoryを生成しない。

---

# Design Decisions

ProductionDelegateは、
Productionに対する管理権限の委任を表す。

ProductionDelegateはProductionの子Entityとして管理する。

PrimaryManagerはProductionに一人だけ存在する。

PrimaryManagerはProductionに関する全権限を持つ。

ProductionDelegateは、
必要に応じて複数設定できる。

ProductionDelegateはPersonを参照する。

ProductionDelegateはDelegateRoleを参照する。

DelegateRoleは権限セットを定義するマスターである。

DelegateRoleはPersonではなく、
ProductionDelegateに紐づく。

同一Personが複数Productionで
異なるDelegateRoleを持つことを許可する。

ProductionDelegateはOrganization Membershipとは
別の権限体系として管理する。

ProductionDelegateはParticipantとは独立して管理する。

ProductionDelegateはHistoryを生成しない。

---

# Future

将来的に以下へ対応する。

- DelegateRoleの追加
- DelegateRoleの変更
- DelegateRoleの廃止
- 権限セットの細分化
- Production単位の権限管理
- Rehearsal管理権限
- Schedule管理権限
- Reservation管理権限
- Participant管理権限
- Performance管理権限
- Document管理権限

DelegateRoleの具体的な定義は
DelegateRole Domainで管理する。

---

# Design Principles

- ProductionDelegateはProductionに対する管理権限の委任を表す。
- ProductionDelegateはProductionの子Entityである。
- ProductionDelegateはPersonを参照する。
- ProductionDelegateはDelegateRoleを参照する。
- DelegateRoleは権限セットを表すマスターである。
- DelegateRoleはProduction単位で適用される。
- PrimaryManagerはProductionに一人だけ存在する。
- PrimaryManagerはProductionに関する全権限を持つ。
- ProductionDelegateは必要に応じて複数設定できる。
- ProductionDelegateはDelegateRoleに定義された権限のみ持つ。
- 同一PersonがProductionごとに異なるDelegateRoleを持つことを許可する。
- Organization MembershipとProductionDelegateの権限を分離する。
- ParticipantとProductionDelegateを分離する。
- ProductionDelegateはPerson自身のRoleではない。
- ProductionDelegateはProductionを経由して管理する。
- ProductionDelegateは監査情報を保持する。
- ProductionDelegateはHistoryを生成しない。
- Business RuleはDomain Layerが管理する。