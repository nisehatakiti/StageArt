# StageArt Blueprint
# API : Person

Version : 1.0

---

# Purpose

Person APIはPersonドメインを操作するためのREST APIを定義する。

PersonはStageArtに登録された人物を表すBusiness Resourceである。

Personは認証情報(Account)とは独立したDomainであり、
プロフィール、所属情報、およびHistoryを統合して公開する。

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
- Public Organizations
- History

Historyは独立したDomainであるが、
公開APIではPerson Resourceの一部として提供する。

Accountは公開しない。

---

# Create Person

Version 1.0ではPersonを直接作成しない。

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
- Biography
- Profile Image
- Website
- SNS
- Public Organizations
- History

Historyには以下を含む。

- Participation History
- Audience History

Historyは読み取り専用である。

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

Historyは更新できない。

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
- History
- Tag

---

# Authorization

Person情報の閲覧は公開設定に従う。

更新は本人のみ可能とする。

Organization Membershipによる更新権限は持たない。

Historyは更新できない。

---

# Domain Events

Person APIは以下のDomain Eventを利用する。

- PersonProfileUpdated

Historyは以下のDomain Eventによって自動更新される。

- ParticipantAdded
- ParticipantUpdated
- ReservationCreated
- ReservationCheckedIn

将来的に以下を追加する。

- PersonMerged
- PersonArchived

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
- Historyは独立したDomainである。
- HistoryはParticipantおよびReservationから自動生成する。
- HistoryはPerson Resourceの一部として公開する。
- Historyは読み取り専用である。
- Historyを操作するAPIは提供しない。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。
