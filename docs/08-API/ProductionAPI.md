# StageArt Blueprint
# API : Production

Version : 2.0

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

```
/api/v1/organizations/{organizationId}/productions
```

Production固有の操作はProduction Resourceとして公開する。

```
/api/v1/productions/{productionId}
```

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

---

# Create Production

## Request

```
POST /api/v1/organizations/{organizationId}/productions
```

### Request Body

```json
{
  "title": "12人のうかれる人々",
  "categoryId": "...",
  "genreIds": [
    "...",
    "..."
  ]
}
```

### Success

```
201 Created
```

### Business Rules

- Productionを作成する。
- 内部的にProjectを生成する。
- ProductionCreatedを発行する。
- 初期Performanceを生成する。
- 初期設定はDomain Eventによって実行する。

---

# Get Production

## Request

```
GET /api/v1/productions/{productionId}
```

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

```
PUT /api/v1/productions/{productionId}
```

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

```
PATCH /api/v1/productions/{productionId}/publish
```

### Business Rules

- ProductionStatusをPublishedへ変更する。
- ProductionPublishedを発行する。

---

# Archive Production

## Request

```
PATCH /api/v1/productions/{productionId}/archive
```

### Business Rules

- ProductionStatusをArchivedへ変更する。
- ProductionArchivedを発行する。

---

# List Productions

## Request

Organization内の公演一覧

```
GET /api/v1/organizations/{organizationId}/productions
```

公開公演検索

```
GET /api/v1/productions
```

### Query Parameters

```
page

pageSize

keyword

category

genre

tag

status

sort
```

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

```
GET    /productions/{productionId}/performances

GET    /productions/{productionId}/participants
```

---

# Authorization

Productionの作成・更新・公開は
Organization Membershipによって認可する。

Roleに応じて利用可能な操作を制御する。

---

# Domain Events

Production APIは以下のDomain Eventを利用する。

- ProductionCreated
- ProductionPublished
- ProductionArchived

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
- Business RuleはDomain Layerが管理する。
- Domain Eventを利用してBusiness Processを開始する。
- APIはRESTを採用する。
