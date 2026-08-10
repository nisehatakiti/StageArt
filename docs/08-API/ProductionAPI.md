# StageArt Blueprint
# API : Production

Version : 4.0

---

# Purpose

Production APIはProductionドメインを操作するためのREST APIを定義する。

ProductionはOrganizationが管理する公開公演を表すBusiness Resourceである。

Production APIはProductionを中心として、
公演に必要な関連情報を集約して提供する。

ProjectはInternal Domainであるため公開APIには含めない。

Productionには、
公演の管理責任者であるPrimaryManagerと、
必要に応じて設定されるProductionDelegateが存在する。

PrimaryManagerおよびProductionDelegateは、
Productionに紐づく情報を誰が更新できるかを決定するために利用する。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

ProductionはOrganization配下のResourceとして公開する。

/api/v1/organizations/{organizationId}/productions

Production固有の操作はProduction Resourceとして公開する。

/api/v1/productions/{productionId}

---

# Public Resource

Production APIが公開する情報

- Production
- Performances
- Participants
- Category
- Genres
- Tags

Projectは公開しない。

PrimaryManagerおよびProductionDelegateは
管理権限情報であるため、
一般公開Resourceとして無条件には公開しない。

利用者の権限に応じて管理情報を取得できる。

---

# Create Production

## Request

POST /api/v1/organizations/{organizationId}/productions

### Request Body

{
  "title": "12人のうかれる人々",
  "categoryId": "...",
  "genreIds": [
    "...",
    "..."
  ]
}

### Success

201 Created

### Business Rules

- Productionを作成する。
- 内部的にProjectを生成する。
- ProductionCreatedを発行する。
- 初期Performanceを生成する。
- 初期設定はDomain Eventによって実行する。
- Production作成者に対応するPersonをPrimaryManagerとして設定する。

PrimaryManagerはProduction作成時に必ず設定する。

PrimaryManagerはProductionに関する全権限を持つ。

---

# Get Production

## Request

GET /api/v1/productions/{productionId}

### Response

取得可能情報

- Production
- Performances
- Participants
- Category
- Genres
- Tags

管理権限を持つ利用者は、
以下の管理情報も取得できる。

- PrimaryManager
- ProductionDelegates
- DelegateRole

Production APIは関連Resourceを集約して返却する。

---

# Update Production

## Request

PUT /api/v1/productions/{productionId}

更新可能項目

- Title
- Catch Copy
- Description
- Image
- Category
- Genres
- Tags

ProductionIdは変更できない。

Productionの更新には、
Productionに対する更新権限が必要である。

PrimaryManagerは更新可能なすべてのProduction情報を変更できる。

ProductionDelegateは、
割り当てられたDelegateRoleに定義された権限の範囲で更新できる。

---

# Change Primary Manager

## Request

PATCH /api/v1/productions/{productionId}/primary-manager

### Request Body

{
  "personId": "person-001"
}

### Business Rules

- ProductionのPrimaryManagerを変更する。
- 新しいPersonをPrimaryManagerとして設定する。
- ProductionPrimaryManagerChangedを発行する。

PrimaryManagerは一つのProductionにつき一人のみ設定する。

PrimaryManagerの変更後、
新しいPrimaryManagerがProductionに関する全権限を持つ。

過去に実行された操作のCreatedByおよびUpdatedByは変更しない。

PrimaryManagerの変更は、
Productionに対する管理権限を持つPrimaryManagerが実行できる。

ProductionDelegateは、
DelegateRoleにPrimaryManager変更権限が明示的に定義されない限り、
PrimaryManagerを変更できない。

---

# List Production Delegates

## Request

GET /api/v1/productions/{productionId}/delegates

### Response

Productionに設定されたProductionDelegate一覧を返却する。

取得可能情報

- ProductionDelegateId
- Person
- DelegateRole
- CreatedAt
- CreatedBy
- UpdatedAt
- UpdatedBy

---

# Add Production Delegate

## Request

POST /api/v1/productions/{productionId}/delegates

### Request Body

{
  "personId": "person-002",
  "delegateRoleId": "rehearsal-manager"
}

### Business Rules

- ProductionDelegateを作成する。
- Productionへ紐付ける。
- Personへ紐付ける。
- DelegateRoleへ紐付ける。
- ProductionDelegateAddedを発行する。

ProductionDelegateは一つのProductionに対して
複数設定できる。

同一Personを複数のProductionのDelegateとして設定できる。

同一Personであっても、
Productionごとに異なるDelegateRoleを設定できる。

---

# Update Production Delegate

## Request

PUT /api/v1/productions/{productionId}/delegates/{productionDelegateId}

更新可能項目

- Person
- DelegateRole

ProductionDelegateIdは変更できない。

Personを変更する場合は、
既存のProductionDelegateを別Personへ差し替える。

DelegateRoleを変更する場合は、
新しいDelegateRoleへ変更する。

変更完了時に、

- UpdatedBy
- UpdatedAt

を更新する。

DelegateRoleの変更によって、
ProductionDelegateが持つ権限も変更される。

ProductionDelegateUpdatedを発行する。

---

# Remove Production Delegate

## Request

DELETE /api/v1/productions/{productionId}/delegates/{productionDelegateId}

### Business Rules

- ProductionDelegateをProductionから削除する。
- ProductionDelegateRemovedを発行する。

削除後、
対象PersonはProduction単位のDelegate権限を失う。

過去に実行された操作のCreatedByおよびUpdatedByは変更しない。

---

# Delegate Role

DelegateRoleは、
ProductionDelegateへ付与する権限セットを表すマスターである。

DelegateRole自体はProductionごとに作成しない。

ProductionDelegateから
あらかじめ定義されたDelegateRoleを参照する。

例）

REHEARSAL_MANAGER
RESERVATION_MANAGER
PARTICIPANT_MANAGER
PERFORMANCE_MANAGER

DelegateRoleが持つ具体的な権限は
Authorization設計で定義する。

---

# Delegate Role Scope

DelegateRoleによる権限は、
Production単位で有効となる。

例えば、

Production A
    ↓
Person A
    ↓
REHEARSAL_MANAGER

と、

Production B
    ↓
Person A
    ↓
RESERVATION_MANAGER

を同時に設定できる。

Person自身のRoleやOrganization Membershipによって
ProductionDelegateのRoleが決定されることはない。

---

# Publish Production

## Request

PATCH /api/v1/productions/{productionId}/publish

### Business Rules

- ProductionStatusをPublishedへ変更する。
- ProductionPublishedを発行する。

公開にはProductionに対する公開権限が必要である。

PrimaryManagerは公開できる。

ProductionDelegateは、
DelegateRoleに公開権限が定義されている場合に公開できる。

---

# Archive Production

## Request

PATCH /api/v1/productions/{productionId}/archive

### Business Rules

- ProductionStatusをArchivedへ変更する。
- ProductionArchivedを発行する。

アーカイブにはProductionに対するアーカイブ権限が必要である。

PrimaryManagerはアーカイブできる。

ProductionDelegateは、
DelegateRoleにアーカイブ権限が定義されている場合にアーカイブできる。

---

# List Productions

## Request

Organization内の公演一覧

GET /api/v1/organizations/{organizationId}/productions

公開公演検索

GET /api/v1/productions

### Query Parameters

page
pageSize
keyword
category
genre
tag
status
sort

---

# Search

検索対象

- Title
- Catch Copy
- Description
- Category
- Genre
- Tag
- Participant
- Organization

PrimaryManagerおよびProductionDelegateは、
一般公開検索の対象としない。

---

# Child Resources

Production配下の公開Resource

GET /api/v1/productions/{productionId}/performances

GET /api/v1/productions/{productionId}/participants

PerformanceはProductionに属する公演回を表す。

ReservationはPerformanceに対して作成される。

Check InもPerformanceを対象として実行される。

---

# Performance Context

Productionは複数のPerformanceを持つことができる。

Performanceは、
Productionにおける個別の公演回を表す。

例えば、

Production
    ↓
    ├── 昼公演
    ├── 夜公演
    └── 別日公演

という構造になる。

ReservationはProductionそのものではなく、
特定のPerformanceに対して作成する。

Check Inを行う際も、
受付担当者がProductionおよびPerformanceを選択し、
選択されたPerformanceをCheck In対象とする。

そのため、

Reservation.Performance
    =
Check In対象Performance

であることを確認する。

一致しない場合はCheck Inできない。

---

# Reservation Relationship

Production APIはReservationを直接管理しない。

ReservationはPerformanceに対する予約として
Reservation Domainで管理する。

関係は以下のようになる。

Production
    ↓
Performance
    ↓
Reservation

Production APIは、
Reservationの作成・変更・キャンセル・Check Inを
直接実行しない。

Reservationに関する操作はReservation APIが担当する。

---

# Check In Relationship

Check InはReservationに対して実行する。

Check In開始時には、

Production
    ↓
Performance
    ↓
Reservation

という対象関係を確定する。

受付担当者は最初にProductionを選択し、
その後Performanceを選択する。

選択したPerformance以外のReservationは
Check In対象としない。

---

# Authorization

Production APIの認可は、
Organization MembershipとProduction単位の管理権限を
組み合わせて判定する。

---

# Organization Membership

Organization Membershipは、
Organization単位の所属・権限を表す。

Organization Membershipによって、
Organizationに対する操作権限を制御する。

---

# Production Primary Manager Authorization

PrimaryManagerは、
Productionに関する全権限を持つ。

PrimaryManagerは、
DelegateRoleによる制限を受けない。

PrimaryManagerは以下を実行できる。

- Production情報の更新
- Performance等のProduction関連情報の管理
- PrimaryManagerの変更
- ProductionDelegateの追加
- ProductionDelegateの変更
- ProductionDelegateの削除
- DelegateRoleの設定変更
- Productionの公開
- Productionのアーカイブ

各DomainのBusiness Ruleによって
個別に制約される操作は、そのDomainのRuleに従う。

---

# Production Delegate Authorization

ProductionDelegateは、
設定されたDelegateRoleに定義された権限のみを持つ。

DelegateRoleに権限がない操作は実行できない。

ProductionDelegateの権限は
そのProductionに対してのみ有効である。

ProductionDelegateは、
Organization Membershipを変更する権限を持たない。

ProductionDelegateの権限は、
ProductionDelegateが設定されている間のみ有効である。

ProductionDelegateを削除すると、
そのPersonはProduction単位の委任権限を失う。

---

# Management Authorization

Productionに紐づく各Business Resourceの更新可否は、
以下の順序で判定する。

1. PrimaryManager
   ↓
   全権限

2. ProductionDelegate
   ↓
   DelegateRole
   ↓
   定義された権限

3. 権限なし
   ↓
   操作不可

Organization Membershipによる権限と
Production単位の権限は混同しない。

---

# Management Scope

Production単位の管理権限は、
Productionに紐づく情報を更新するために利用する。

将来的に以下の管理へ利用できる。

- Production
- Performance
- Participant
- Reservation
- Rehearsal
- Schedule
- Task
- Document

PrimaryManagerはこれらの管理権限をすべて持つ。

ProductionDelegateは、
DelegateRoleに定義された範囲のみ管理できる。

---

# Audit Information

ProductionDelegateの追加・変更・削除では、
以下の監査情報を記録する。

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

PrimaryManagerの変更についても、
操作を実行した利用者を監査情報として記録する。

過去に実行された操作のCreatedByおよびUpdatedByは、
管理者変更によって書き換えない。

---

# Domain Events

Production APIは以下のDomain Eventを利用する。

- ProductionCreated
- ProductionPublished
- ProductionArchived
- ProductionPrimaryManagerChanged
- ProductionDelegateAdded
- ProductionDelegateUpdated
- ProductionDelegateRemoved

Reservationに関するDomain Eventは
Production APIでは発行しない。

Reservationに関するDomain Eventは
Reservation Domainが発行する。

---

# Error Response

代表例

400 Bad Request

401 Unauthorized

403 Forbidden

404 Not Found

409 Conflict

422 Validation Error

500 Internal Server Error

---

# Future

将来的に以下を追加する。

- Gallery
- Trailer
- Reviews
- Related Productions
- Streaming
- Rehearsal Management
- Schedule Management
- Task Management
- Production-specific Authorization

DelegateRoleは、
将来追加されるDomainに対応して拡張する。

Production APIは必要に応じて関連Resourceを集約して公開する。

---

# Design Principles

- Productionは公開Business Resourceである。
- Organization配下のResourceとして管理する。
- ProjectはInternal Domainとして隠蔽する。
- Production APIは関連Resourceを集約して公開する。
- PerformanceはProductionに属する公演回を表す。
- ReservationはPerformanceに対して作成する。
- Check InはPerformanceを対象として実行する。
- Production APIはReservationを直接管理しない。
- ReservationはReservation APIが管理する。
- Check Inに関するBusiness RuleはReservation Domainが管理する。
- ProductionとReservationの間に直接的な予約操作を作らない。
- Productionは一人のPrimaryManagerを持つ。
- PrimaryManagerはPersonを参照する。
- PrimaryManagerはProductionに関する全権限を持つ。
- Productionは0人以上のProductionDelegateを持つ。
- ProductionDelegateはPersonを参照する。
- ProductionDelegateはDelegateRoleを参照する。
- DelegateRoleはあらかじめ定義された権限セットを表す。
- DelegateRoleはProduction単位で適用される。
- 同一PersonがProductionごとに異なるDelegateRoleを持つことを許可する。
- Organization MembershipとProduction単位の権限を分離する。
- ProductionDelegateはPerson自身のRoleではない。
- PrimaryManagerにはDelegateRoleを設定しない。
- ProductionDelegateは設定された権限のみ持つ。
- 管理権限情報は一般公開情報ではない。
- ProductionDelegateの追加・変更・削除は監査情報を保持する。
- 管理権限の変更は過去のCreatedByおよびUpdatedByを変更しない。
- Business RuleはDomain Layerが管理する。
- Domain Eventを利用してBusiness Processを開始する。
- APIはRESTを採用する。