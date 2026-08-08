# StageArt Blueprint
# API : Person

Version : 1.0

---

# Purpose

Person APIはPersonドメインを操作するためのREST APIを定義する。

PersonはStageArtに登録された人物を表す。

Personは認証情報(Account)とは独立したBusiness Resourceであり、
プロフィール、出演実績、観劇履歴などの公開情報を管理する。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

```
/api/v1/persons
```

Person固有の操作はPerson Resourceとして公開する。

```
/api/v1/persons/{personId}
```

---

# Public Resource

Person APIが公開する情報

- Person Profile
- Participation History
- Audience History
- Organization Membership（公開設定のみ）

認証情報(Account)は公開しない。

---

# Create Person

Version 1.0ではPersonは直接作成しない。

Personは以下の場合に自動生成される。

- Account作成時
- 招待承認時（将来）
- Companionとの統合時（将来）

Person作成はDomain Layerが管理する。

---

# Get Person

## Request

```
GET /api/v1/persons/{personId}
```

### Response

取得可能情報

- Display Name
- Profile
- Profile Image
- SNS
- Website
- Public Organizations
- Participation History
- Audience History

---

# Update Person

## Request

```
PUT /api/v1/persons/{personId}
```

更新可能項目

- Display Name
- Biography
- Profile Image
- Website
- SNS
- Public Settings

PersonIdは変更できない。

---

# List Persons

## Request

```
GET /api/v1/persons
```

### Query Parameters

```
page

pageSize

keyword

organization

tag

sort
```

---

# Search

検索対象

- Display Name
- Biography
- Organization
- Participation History
- Tag

---

# Child Resources

Person配下の公開Resource

```
GET /persons/{personId}/participations

GET /persons/{personId}/audience-history
```

Version 1.0では読み取り専用とする。

ParticipationおよびAudience Historyは
Domain Eventによって自動更新される。

---

# Authorization

Person情報の閲覧は公開設定に従う。

本人のみ更新可能とする。

Organization Membershipによる更新権限は持たない。

---

# Domain Events

Person APIは以下のDomain Eventを利用する。

- PersonProfileUpdated

将来的に以下を追加する。

- PersonMerged
- PersonArchived

Participation HistoryおよびAudience Historyは
Domain Eventによって更新される。

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

将来的に以下へ対応する。

- Portfolio
- Award History
- Skill
- Favorite
- Follow
- Verification Badge

---

# Design Principles

- Personは人物を表すBusiness Resourceである。
- PersonはAccountとは独立したDomainである。
- Accountは公開APIとして提供しない。
- Participation Historyは自動生成する。
- Audience Historyは自動生成する。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。
