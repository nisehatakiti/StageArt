# StageArt Blueprint
# API Design

Version : 1.0

---

# Purpose

本章ではStageArtで採用するAPI設計の基本方針を定義する。

APIはPresentation LayerとApplication Layerを接続するインターフェースである。

Business Ruleは保持せず、
Domain Layerの機能を公開する責務のみを持つ。

---

# API Style

StageArtはREST APIを採用する。

APIはCommandではなく、
Business Resourceを中心として設計する。

Business RuleはDomain Layerが管理し、
APIはその公開窓口として機能する。

---

# Public Domain Rule

APIは公開ドメインのみを公開する。

Internal DomainはAPIとして公開しない。

利用者はInternal Domainの存在を意識しない。

Internal Domainとの連携はApplication Layerが担当する。

例）

公開するDomain

- Organization
- Production
- Performance
- Reservation
- Person

公開しないDomain

- Project
- Workflow
- Checklist
- Document Workspace
- Budget Workspace

---

# Resource Design

APIで公開するResourceはDomain Modelと対応する。

例）

- Organization
- Person
- Production
- Performance
- Reservation

ResourceはBusiness上の概念を表現する。

---

# URI Design

URIは名詞を利用する。

複数形で表現する。

例）

```
/organizations

/productions

/performances

/reservations
```

Commandを表すURIは使用しない。

例）

```
/createReservation

/updateProduction

/deleteOrganization
```

---

# Parent Resource Rule

Resourceが別Resourceへ所属する場合は、
親Resource配下として表現する。

作成・一覧取得は親Resourceを利用する。

個別取得・更新・削除は対象Resourceを利用する。

例）

```
POST /organizations/{organizationId}/productions

GET /organizations/{organizationId}/productions

GET /productions/{productionId}

PUT /productions/{productionId}

PATCH /productions/{productionId}/publish
```

このルールをStageArt全体で統一する。

---

# HTTP Method

HTTP MethodはRESTの意味に従う。

| Method | Purpose |
|---------|---------|
| GET | 取得 |
| POST | 作成 |
| PUT | 全体更新 |
| PATCH | 部分更新 |
| DELETE | 削除 |

Business RuleはHTTP Methodではなく、
Domain Layerが管理する。

---

# Request

RequestはResourceに対する操作を表現する。

ValidationはApplication Layerで実施する。

Business RuleはDomain Layerで実施する。

---

# Response

ResponseはJSONを利用する。

基本構造

```json
{
  "data": {},
  "meta": {},
  "links": {}
}
```

Validation Errorなどは

```json
{
  "errors": []
}
```

で返却する。

---

# Error Response

HTTP Status Codeを利用する。

| Code | Meaning |
|------|---------|
|400|Bad Request|
|401|Unauthorized|
|403|Forbidden|
|404|Not Found|
|409|Conflict|
|422|Validation Error|
|500|Internal Server Error|

---

# Authentication

Version 1.0では以下をサポートする。

- Google Login
- Email Login

認証方式はAPI利用者から隠蔽する。

---

# Authorization

認可はMembershipによって行う。

Accountでは判定しない。

Organizationへの所属とRoleによって
利用可能なAPIを決定する。

---

# Versioning

API VersionはURLで管理する。

例）

```
/api/v1/
```

Business RuleはVersionへ依存しない。

---

# Pagination

一覧APIはPaginationをサポートする。

```
page

pageSize
```

将来的にCursor Paginationへ対応可能とする。

---

# Filtering

一覧APIはFilterをサポートする。

例）

```
?status=published

?category=play

?genre=comedy
```

---

# Sorting

一覧APIはSortをサポートする。

例）

```
?sort=name

?sort=-createdAt
```

---

# Domain Events

Business ProcessはDomain Eventを利用して開始する。

APIはEventを発行するだけであり、
Business Processは保持しない。

例）

```
POST Organization

↓

OrganizationCreated

↓

Business Process
```

---

# Design Principles

- APIはRESTを採用する。
- APIは公開Domainのみを公開する。
- Internal Domainは公開しない。
- ResourceはDomain Modelと対応する。
- 親子関係はURIで表現する。
- 作成・一覧取得は親Resource配下で行う。
- 個別取得・更新・削除は対象Resourceで行う。
- Business RuleはDomain Layerが管理する。
- APIはBusiness Ruleを持たない。
- APIはDomain Eventを発行する。
- JSONを利用する。
- API Versionを管理する。
