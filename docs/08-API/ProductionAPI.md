# StageArt Blueprint
# API : Production

Version : 3.0

---

# Purpose

Production APIはProductionドメインを操作するためのREST APIを定義する。

ProductionはOrganizationが管理する公開公演を表すBusiness Resourceである。

Production APIはProductionを中心として、
公演に必要な関連情報を集約して提供する。

ProjectはInternal Domainであるため公開APIには含めない。

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

利用者はProjectの存在を意識しない。

ReservationおよびCheck Inは
Production APIの責務ではない。

ReservationはReservation APIが管理し、
Check InはReservation APIおよびCheck In Portalから
Performanceを対象として実行する。

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

---

# Publish Production

## Request

PATCH /api/v1/productions/{productionId}/publish

### Business Rules

- ProductionStatusをPublishedへ変更する。
- ProductionPublishedを発行する。

---

# Archive Production

## Request

PATCH /api/v1/productions/{productionId}/archive

### Business Rules

- ProductionStatusをArchivedへ変更する。
- ProductionArchivedを発行する。

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
Performance
    ├─ 昼公演
    ├─ 夜公演
    └─ 別日公演

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

Productionの作成・更新・公開は
Organization Membershipによって認可する。

Roleに応じて利用可能な操作を制御する。

Production APIの権限と、
Reservation APIの権限は分離する。

Check Inに必要な権限は、
ReservationおよびCheck Inの業務権限として管理する。

---

# Domain Events

Production APIは以下のDomain Eventを利用する。

- ProductionCreated
- ProductionPublished
- ProductionArchived

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
- Business RuleはDomain Layerが管理する。
- Domain Eventを利用してBusiness Processを開始する。
- APIはRESTを採用する。