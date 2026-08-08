# StageArt Blueprint
# API : Organization

Version : 1.0

---

# Purpose

Organization APIはOrganizationドメインを操作するためのREST APIを定義する。

Organization APIは劇団・プロデュース団体・演劇サークルなど、
舞台芸術活動を行う団体の管理を担当する。

Organizationは公開Resourceであり、
Projectなどの内部ドメインはAPIとして公開しない。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

```
/api/v1/organizations
```

---

# Public Resource

Organization APIが公開する情報

- Organization
- Member
- Production

Organizationに関連する内部ドメイン(Projectなど)は公開しない。

利用者はProjectの存在を意識しない。

---

# Create Organization

## Request

```
POST /api/v1/organizations
```

### Request Body

```json
{
  "name": "劇団StageArt",
  "description": "千葉県で活動する劇団です。"
}
```

### Success

```
201 Created
```

### Business Rules

- Organizationを新規作成する。
- OrganizationCreatedを発行する。
- 作成者をOwnerとしてMembershipへ登録する。
- 初期設定はDomain Eventによって実行する。

---

# Get Organization

## Request

```
GET /api/v1/organizations/{organizationId}
```

---

# Update Organization

## Request

```
PUT /api/v1/organizations/{organizationId}
```

更新可能項目

- Name
- Description
- Logo
- Website
- SNS

OrganizationIdは変更できない。

---

# Archive Organization

## Request

```
PATCH /api/v1/organizations/{organizationId}/archive
```

### Business Rules

- OrganizationStatusをArchivedへ変更する。
- OrganizationArchivedを発行する。
- 過去データは保持する。

---

# Delete Organization

Version 1.0では物理削除しない。

Organizationは論理削除を採用する。

---

# List Organizations

## Request

```
GET /api/v1/organizations
```

### Query Parameters

```
page

pageSize

keyword

status

sort
```

---

# Search

検索対象

- Organization Name
- Description
- Activity Region

---

# Child Resources

Organization配下の公開Resource

```
GET    /organizations/{id}/members

GET    /organizations/{id}/productions

GET    /organizations/{id}
```

Version 1.0では上記を公開する。

ProjectはInternal Domainであるため、
公開APIとして提供しない。

---

# Authorization

認可はMembershipによって行う。

Roleに応じて実行可能なAPIを制御する。

例

- Owner
- Administrator
- Manager
- Member

---

# Domain Events

Organization APIは以下のDomain Eventを利用する。

- OrganizationCreated
- OrganizationArchived

将来的に

- OrganizationDeleted

を追加する。

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

- Organization Invite
- Organization Statistics
- Organization Homepage
- Organization Settings

これらはOrganization Resourceの配下として公開する。

---

# Design Principles

- Organizationは公開Resourceである。
- ProjectはInternal Domainであり公開しない。
- APIはRESTを採用する。
- Business RuleはDomain Layerが管理する。
- Domain Eventを利用してBusiness Processを開始する。
- Organizationは論理削除を採用する。
