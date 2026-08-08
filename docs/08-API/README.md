# StageArt Blueprint
# API Design

Version : 1.0

---

# Purpose

本章ではStageArtで採用するAPI設計の基本方針を定義する。

APIはPresentation LayerとApplication Layerを接続するインターフェースである。

Business Ruleは保持せず、
Domain Layerの機能を外部へ公開する責務のみを持つ。

StageArtのAPIはBlueprintに定義されたDomain Modelを公開するためのインターフェースであり、
Domain Modelを変更しない限りAPI設計も変更しない。

---

# Design Philosophy

StageArtはDomain Driven Design(DDD)を採用する。

APIはDomain Modelをそのまま公開するものではなく、
Business Resourceとして表現する。

利用者はBusiness Resourceのみを操作し、
内部構造を意識しない。

これはGolden Ruleで定義する

「利用者は内部構造を意識しない」

という原則を実現するためである。

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

Application Layerが公開ドメインとInternal Domainを連携させる。

## Public Domain

- Organization
- Person
- Production
- Performance
- Participant
- Reservation

## Internal Domain

- Project
- Workflow
- Checklist
- Document Workspace
- Budget Workspace
- Notification Queue

Internal Domainは将来的に追加されても、
公開APIへ影響を与えない。

---

# Resource Design

APIで公開するResourceはDomain Modelと対応する。

ResourceはBusiness上の概念を表現する。

例）

- Organization
- Person
- Production
- Performance
- Reservation

Resource名はBusiness Languageを利用する。

DatabaseやInfrastructureの概念を公開しない。

---

# URI Design

URIは名詞を利用する。

Resourceは複数形で表現する。

例

```
/organizations

/productions

/performances

/reservations
```

Commandを表すURIは使用しない。

例

```
/createReservation

/updateProduction

/deleteOrganization
```

---

# Parent Resource Rule

Resourceが他Resourceへ所属する場合は、
URIでも親子関係を表現する。

作成(Create)

一覧(List)

は親Resource配下で行う。

取得(Get)

更新(Update)

削除(Delete)

は対象Resource自身を利用する。

例

```
POST /organizations/{organizationId}/productions

GET /organizations/{organizationId}/productions

GET /productions/{productionId}

PUT /productions/{productionId}

PATCH /productions/{productionId}/publish
```

StageArt全体でこのルールを統一する。

---
# Aggregate Rule

公開APIはAggregate Rootのみを公開する。

Aggregate内部の子Entityは独立したAPIとして公開しない。

子Entityの生成・更新・削除は、
必ずAggregate Rootを経由して行う。

これによりAggregateの整合性を維持する。

例）

```
Reservation
    ├── Companion
    └── ReservationSeat
```

CompanionおよびReservationSeatは
独立したAPIを持たない。

以下のAPIは提供しない。

```
/companions

/reservation-seats
```

これらの更新は

```
PUT /reservations/{reservationId}
```

を通じて実施する。

将来的に新しい子Entityが追加されても、
同じルールを適用する。
# HTTP Method

HTTP MethodはRESTの意味に従う。

| Method | Purpose |
|---------|---------|
| GET | Resource取得 |
| POST | Resource作成 |
| PUT | Resource全体更新 |
| PATCH | Resource部分更新 |
| DELETE | Resource削除 |

Business RuleはHTTP Methodではなく、
Domain Layerが管理する。

---

# Request

RequestはResourceへの操作を表現する。

ValidationはApplication Layerで実施する。

Business RuleはDomain Layerで実施する。

RequestはBusiness Commandを表現しない。

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

ResponseはPresentation用DTOであり、
Domain Entityを直接返却しない。

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

Business ErrorはHTTP Errorではなく、
Business Ruleとして扱う。

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

例

```
?status=published

?category=play

?genre=comedy
```

---

# Sorting

一覧APIはSortをサポートする。

例

```
?sort=name

?sort=-createdAt
```

---

# Versioning

API VersionはURLで管理する。

例

```
/api/v1/
```

Business RuleはVersionへ依存しない。

---

# Domain Events

APIはBusiness Processを実装しない。

Business ProcessはDomain Eventを契機として開始する。

例

```
POST Organization

↓

OrganizationCreated

↓

Create Default Membership

↓

Create Default Settings

↓

Create Document Space
```

APIはBusiness Eventの入口であり、
Business Logicの実行主体ではない。

---

# Layer Responsibility

Presentation Layer

↓

API

↓

Application Layer

↓

Domain Layer

↓

Infrastructure Layer

APIはPresentation LayerとApplication Layerの境界である。

Business RuleはDomain Layerのみが保持する。

---

# Future

将来的に

- Mobile App
- LINE Integration
- Public API
- External API
- GraphQL

などが追加されても、
Domain Modelを変更しない限りBusiness Ruleは変更しない。

---

# Design Principles

- APIはRESTを採用する。
- APIは公開Domainのみを公開する。
- Internal Domainは公開しない。
- APIはBusiness Resourceを公開する。
- ResourceはDomain Modelと対応する。
- 親子関係はURIで表現する。
- 公開APIはAggregate Rootのみを公開する。   ←追加
- 作成・一覧取得は親Resource配下で行う。
- 個別取得・更新・削除は対象Resourceを利用する。
- APIはBusiness Ruleを持たない。
- ValidationはApplication Layerが担当する。
- Business RuleはDomain Layerが担当する。
- APIはDomain Eventの入口となる。
- APIはPresentation用DTOを返却する。
- Domain Entityを直接公開しない。
- Domain Modelを唯一の設計基準とする。
