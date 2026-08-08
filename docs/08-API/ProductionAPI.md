# StageArt Blueprint
# API : Production

Version : 1.0

---

# Purpose

Production APIはProductionドメインを操作するためのREST APIを定義する。

Productionは観客へ公開される公演を表す。

ProjectはInternal Domainであり、
APIからは公開しない。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

```
/api/v1/productions
```

---

# Public Resource

Production APIが公開する情報

- Production
- Performance
- Participant
- Category
- Genre
- Tag

Projectは公開しない。

利用者はProjectを意識しない。

---

# Create Production

## Request

```
POST /api/v1/productions
```

### Request Body

```json
{
    "organizationId": "...",
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

- Productionを新規作成する。
- Projectを内部生成する。
- ProductionCreatedを発行する。
- 初期Performanceを生成する。
- 初期設定はDomain Eventによって実行する。

---

# Get Production

## Request

```
GET /api/v1/productions/{productionId}
```

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
- Genre
- Tag

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

organization

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
GET    /productions/{id}/performances

GET    /productions/{id}/participants

GET    /productions/{id}
```

---

# Authorization

Productionの作成・更新・公開は
Organization Membershipによって認可する。

Roleに応じて操作を制御する。

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

---

# Design Principles

- Productionは公開Resourceである。
- ProjectはInternal Domainとして隠蔽する。
- Business RuleはDomain Layerが管理する。
- Domain Eventを利用してBusiness Processを開始する。
- APIはRESTを採用する。
