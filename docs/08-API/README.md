# StageArt Blueprint
# API Design

Version : 1.0

---

# Purpose

本章ではStageArtで採用するAPI設計の基本方針を定義する。

APIはPresentation LayerとApplication Layerを接続するインターフェースであり、
Business Ruleを保持しない。

Business RuleはDomain Layerで管理する。

---

# API Style

StageArtはREST APIを採用する。

APIはResourceを中心に設計する。

CommandではなくResourceを公開する。

例）

```
GET    /organizations

GET    /productions

POST   /reservations
```

---

# Resource

APIで公開するResourceはDomain Modelに対応する。

例）

- Account
- Person
- Organization
- Project
- Production
- Performance
- Reservation

内部専用Domainは公開しない。

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

以下のようなURIは使用しない。

```
/createReservation

/getPerformance

/updateProduction
```

---

# HTTP Method

HTTP MethodはRESTの意味に従う。

GET

取得

POST

新規作成

PUT

全体更新

PATCH

部分更新

DELETE

削除

---

# Request

RequestはBusiness Commandではなく、

Resourceへの操作を表現する。

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

必要に応じて

```
errors
```

を返却する。

---

# Error Response

ErrorはHTTP Status Codeを利用する。

例）

400

Bad Request

401

Unauthorized

403

Forbidden

404

Not Found

409

Conflict

422

Validation Error

500

Internal Server Error

---

# Authentication

Version 1.0

Google Login

Email Login

認証後はアクセストークンを利用する。

認証方式はAPI利用者から隠蔽する。

---

# Authorization

認可はMembershipを利用する。

Account単位では判定しない。

Organizationへの所属とRoleによって利用可能APIを決定する。

---

# Versioning

API VersionをURLで管理する。

例）

```
/api/v1/organizations
```

Version変更時もBusiness Ruleは変更しない。

---

# Pagination

一覧APIはPaginationをサポートする。

基本形式

```
page

pageSize
```

将来的にCursor Paginationへ変更可能とする。

---

# Filtering

一覧取得はFilterを利用する。

例）

```
?status=published

?category=play

?genre=comedy
```

---

# Sorting

一覧取得はSortをサポートする。

例）

```
?sort=name

?sort=-createdAt
```

---

# Design Principles

- APIはRESTを採用する。
- Resource中心に設計する。
- URIは名詞を利用する。
- Business RuleはDomain Layerが管理する。
- APIはBusiness Ruleを持たない。
- JSONを利用する。
- APIはVersion管理する。

## Public Domain Rule

APIは公開ドメインのみを公開する。

Internal DomainはAPIとして公開しない。

利用者はInternal Domainの存在を意識しない。

Internal Domainとの連携はApplication Layerが担当する。
