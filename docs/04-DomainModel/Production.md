# StageArt Blueprint
# Domain Model : Production

Version : 2.0

---

# Purpose

ProductionはStageArtにおいて観客へ公開される「公演」を管理するドメインである。

Productionは公演の基本情報、出演者、公演回など、
観客が参照する情報を管理する。

ProductionはProjectから生成される公開情報である。

また、Productionは、
その公演を管理するPrimaryManagerおよび
必要に応じて設定されるProductionDelegateを保持する。

PrimaryManagerおよびProductionDelegateは、
Productionに紐づく情報を誰が更新できるかを決定するために利用する。

---

# Concept

Productionは舞台芸術作品の公開単位である。

利用者が「公演を作る」を実行すると、
StageArtはProjectとProductionを自動生成する。

Productionは観客へ公開される情報を保持し、
Projectは制作活動を管理する。

Productionには、
公演の管理責任者としてPrimaryManagerを設定する。

また、必要に応じて複数のProductionDelegateを設定できる。

---

# Identity

ProductionはProductionIdによって一意に識別される。

公演タイトルは識別子ではない。

同名公演が存在しても問題ない。

---

# Relationship

Productionは必ず一つのProjectに所属する。

Organization
    │
    └── Project
            │
            └── Production
                    ├── PrimaryManager
                    │      └── Person
                    │
                    ├── ProductionDelegate
                    │      ├── Person
                    │      └── DelegateRole
                    │
                    ├── Performance
                    ├── Participant
                    ├── Category
                    ├── Genre
                    └── Tag

Productionは以下のドメインを管理する。

- PrimaryManager
- ProductionDelegate
- Performance
- Participant
- Category
- Genre
- Tag

---

# Primary Manager

PrimaryManagerは、
Productionの管理責任者を表す。

PrimaryManagerはPersonを参照する。

一つのProductionは一人のPrimaryManagerを持つ。

PrimaryManagerはProductionに関する
すべての管理権限を持つ。

PrimaryManagerにはDelegateRoleを設定しない。

PrimaryManagerは、
Productionに紐づく情報を管理・更新するための
最上位の権限を持つ。

---

# Primary Manager Assignment

Production作成時にPrimaryManagerを設定する。

通常は、
Productionを作成した利用者に対応するPersonを
PrimaryManagerとして設定する。

PrimaryManagerは必要に応じて変更できる。

PrimaryManagerを変更しても、
過去に実行された操作のCreatedByやUpdatedByは変更しない。

---

# Production Delegate

ProductionDelegateは、
PrimaryManagerからProductionの管理権限を委任されたPersonを表す。

ProductionDelegateはProductionに属する子Entityとして管理する。

ProductionDelegateは必ず一つのPersonを参照する。

ProductionDelegateは一つのDelegateRoleを参照する。

一つのProductionには、
0人以上のProductionDelegateを設定できる。

例えば、

Production
    │
    ├── PrimaryManager
    │      └── Person A
    │
    └── ProductionDelegate
           ├── Person B
           ├── Person C
           └── Person D

という構造を持つ。

---

# Delegate Role

DelegateRoleは、
ProductionDelegateへ付与する権限セットを表すマスターである。

DelegateRoleそのものはPersonに紐づかない。

ProductionDelegateを介して、
特定のProductionにおけるPersonの権限を定義する。

例えば、

REHEARSAL_MANAGER
    ↓
稽古管理に必要な権限

RESERVATION_MANAGER
    ↓
予約管理に必要な権限

PARTICIPANT_MANAGER
    ↓
Participant管理に必要な権限

などを定義できる。

---

# Delegate Role Scope

DelegateRoleによって付与される権限は、
そのProductionに対してのみ有効である。

同じPersonであっても、
別のProductionでは異なるDelegateRoleを設定できる。

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

という設定を許可する。

ProductionDelegateの権限は、
Person自身の権限やOrganization Membershipとは別に管理する。

---

# Authorization

Productionに対する管理権限は、
以下の順序で判定する。

PrimaryManager
    ↓
全権限

ProductionDelegate
    ↓
DelegateRole
    ↓
定義された権限セット

Organization Membership
    ↓
Organization単位の権限

PrimaryManagerは、
DelegateRoleの設定に関係なく全権限を持つ。

ProductionDelegateは、
設定されたDelegateRoleに定義された権限のみを持つ。

---

# Management Scope

PrimaryManagerおよびProductionDelegateは、
Productionに紐づく情報の更新権限を持つ。

対象となる情報は、
各DomainのBusiness Ruleおよび
DelegateRoleによって定義される。

将来的に、

- Performance
- Participant
- Reservation
- Rehearsal
- Schedule
- Task
- Document

などの管理権限をProduction単位で委任できる。

PrimaryManagerはこれらの管理権限をすべて持つ。

ProductionDelegateは、
DelegateRoleに設定された範囲のみ管理できる。

---

# Participant

Productionには複数のParticipantが登録される。

Participantは、

- Person
- Organization

のどちらにも紐付けられる。

出演者・スタッフ・協賛・制作協力などを
統一的に管理する。

Participantの管理権限は、
Productionに対する管理権限として扱う。

---

# Performance

Productionは一つ以上のPerformanceを持つ。

Performanceは実際の公演回を表す。

例）

- 8/1 14:00
- 8/1 18:00
- 8/2 13:00

予約受付はPerformance単位で行う。

Performanceの管理権限は、
Productionに対する管理権限とは別に
DelegateRoleによって必要に応じて制御する。

---

# Public Information

Productionは以下の公開情報を保持する。

- 公演タイトル
- キャッチコピー
- あらすじ
- 公演画像
- 公演期間
- 公演ステータス
- 公開URL

将来的には、

- PV動画
- フライヤー
- ギャラリー

なども追加できる。

PrimaryManagerおよびProductionDelegateは、
観客向けの公開情報ではない。

管理権限情報は公開Resourceとして
無条件に公開しない。

---

# Publication

Productionは公開状態を持つ。

- Draft
- Private
- Published
- Closed
- Archived

PublishedとなったProductionのみ観客へ公開される。

---

# Search

Productionは検索対象となる。

検索条件例

- キーワード
- Category
- Genre
- Tag
- 出演者
- 劇団
- 開催地域
- 開催期間

PrimaryManagerおよびProductionDelegateは、
一般公開検索の対象としない。

---

# History

Production終了後、
StageArtはProductionを削除しない。

ProductionはHistory生成の元データとして
永続的に保持する。

PrimaryManagerやProductionDelegateの変更によって、
過去のHistoryを変更しない。

---

# Management History

ProductionDelegateの追加・変更・削除によって、
過去のProductionにおける活動履歴を変更しない。

管理権限の変更は、
現在以降の認可に影響する。

過去に誰がどの操作を行ったかは、
各Domainで保持されるCreatedByおよびUpdatedByなどの
監査情報によって確認する。

---

# Design Decisions

Productionは公開情報を管理する。

Productionは、
公演に対する管理責任者としてPrimaryManagerを持つ。

Productionは、
必要に応じて複数のProductionDelegateを持つ。

PrimaryManagerはPersonを参照する。

ProductionDelegateはPersonとDelegateRoleを参照する。

PrimaryManagerはProductionに関する全権限を持つ。

ProductionDelegateはDelegateRoleによって
あらかじめ定義された権限のみを持つ。

DelegateRoleはProduction単位で適用される。

Organization Membershipとは別の権限体系として管理する。

ProductionDelegateはPersonの組織Roleではない。

同一PersonがProductionごとに
異なるDelegateRoleを持つことを許可する。

以下はProduction自身では保持しない。

- 稽古
- タスク
- 予算
- 収支
- ドキュメント

これらはProjectまたは将来の各Domainが管理する。

Productionは公開、
Projectは制作、
ProductionManagerは公演単位の認可、
という責務を持つ。

---

# Future

将来的に以下へ対応する。

- 配信公演
- 公演シリーズ
- 関連作品
- レビュー
- アンケート
- ファンクラブ限定公開
- 稽古管理
- スケジュール管理
- タスク管理
- 公演単位の権限管理

ProductionDelegateの権限セットは、
将来追加されるDomainに対応して拡張する。

---

# Design Principles

- Productionは観客へ公開される公演である。
- ProductionはProjectから生成される。
- Productionは公開情報を管理する。
- Productionは一人のPrimaryManagerを持つ。
- PrimaryManagerはPersonを参照する。
- PrimaryManagerはProductionに関する全権限を持つ。
- Productionは0人以上のProductionDelegateを持つ。
- ProductionDelegateはPersonを参照する。
- ProductionDelegateはDelegateRoleを参照する。
- DelegateRoleは権限セットを定義するマスターである。
- DelegateRoleはProduction単位で適用される。
- ProductionDelegateの権限はDelegateRoleによって決定する。
- Organization MembershipとProductionDelegateの権限は分離する。
- 同一Personが複数Productionで異なるDelegateRoleを持つことを許可する。
- PrimaryManagerにはDelegateRoleを設定しない。
- PrimaryManagerは常に全権限を持つ。
- ProductionDelegateは設定された権限のみ持つ。
- 管理権限情報は一般公開情報ではない。
- 管理権限の変更は過去のHistoryを変更しない。
- CreatedByおよびUpdatedByなどの監査情報は各Domainで管理する。
- Productionは終了後も削除しない。
- ProductionはHistory生成の基点となる。