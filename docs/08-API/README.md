# StageArt Blueprint
# API Design

Version : 1.0

---

# Purpose

本章ではStageArtで採用するAPI設計の基本方針を定義する。

APIはPresentation LayerとApplication Layerを接続するインターフェースである。

Business Ruleは保持せず、
Domain Layerの機能をBusiness Resourceとして公開する責務のみを持つ。

StageArtのAPIはBlueprintで定義したDomain Modelを利用者へ提供するためのインターフェースであり、
Domain Modelを変更しない限りAPI設計も変更しない。

---

# Design Philosophy

StageArtはDomain Driven Design（DDD）を採用する。

APIはDomain Modelそのものを公開するものではない。

利用者にとって自然なBusiness Resourceを公開し、
内部構造は隠蔽する。

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

- Account
- Project
- Workflow
- Checklist
- Document Workspace
- Budget Workspace
- Notification Queue

Internal Domainは将来的に追加されても、
公開APIへ影響を与えない。

---

# Public API Rule

DomainとAPIは1対1に対応しない。

公開APIはBusiness Resourceを公開するものであり、
Domain構造をそのまま公開するものではない。

必要な情報はBusiness Resourceへ集約して公開する。

例）

Historyは独立したDomainとして管理する。

しかしHistory APIは提供しない。

HistoryはPerson Resourceへ集約して公開する。

```
GET /persons/{personId}
```

↓

```json
{
  "profile": {},
  "organizations": [],
  "history": []
}
```

Business Resourceとして自然な形を優先する。

---

# Resource Design

APIで公開するResourceはBusiness Resourceとする。

ResourceはBusiness上の概念を表現する。

例）

- Organization
- Person
- Production
- Performance
- Reservation

Database構造やInfrastructureの概念は公開しない。

---

# URI Design

URIは名詞を利用する。

Resourceは複数形で表現する。

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

Resourceが他Resourceへ所属する場合は、
URIでも親子関係を表現する。

作成(Create)

一覧(List)

は親Resource配下で行う。

取得(Get)

更新(Update)

削除(Delete)

は対象Resource自身を利用する。

例）

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

例）

```
Reservation
    ├── Companion
    └── ReservationSeat
```

CompanionおよびReservationSeatは独立したAPIを持たない。

```
PUT /reservations/{reservationId}
```

を通して更新する。

将来的に子Entityが追加されても、
同じルールを適用する。

---

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
|503|Service Unavailable（一時的。再実行可能）|

Business ErrorはHTTP Errorではなく、
Domain Layerで表現する。

## 公演状態・ロック・DB障害の共通エラー（PR #7 レビュー R5）

`stageart/v1` の全エンドポイントで、Controllerが個別に変換しない以下の例外を同じ形で返す（`RestFailureResponder`）。本文はWP RESTのエラー形式 `{ code, message, data: { status } }` とする。

| HTTP | code | 意味 | クライアントの扱い |
|------|------|------|------|
|409|`stageart_production_closed`|公演がCANCELLED / COMPLETED / ARCHIVEDで、その操作を受け付けない（`message` にStatusを含む）|再実行しても成功しない。公演が中止・決算完了済みであることを表示する|
|409|`stageart_capacity_below_reservations`|定員を予約済み席数より少なくしようとした（R6）。`data.capacity`、`data.reserved_seats` 付き。何も変更しない|予約済み席数以上の定員を指定し直してもらう|
|503|`stageart_database_busy`|ロック待ちタイムアウト・デッドロック。トランザクション全体をROLLBACK済みで、何も保存されていない。`data.retryable: true`、`Retry-After: 2` ヘッダー付き|自動では再送しない。「保存されなかったので、少し待ってから再度実行してほしい」と表示し、利用者の操作で再実行する|
|429|`stageart_rate_limited`|操作のレート制限を超えた（PR #7 R14-B。招待の作成・再送、招待の承諾・辞退の失敗）。何も処理していない。`data.retry_after`（時間枠の終了までの秒数）と、同じ秒数の `Retry-After` ヘッダー付き。回数の記録のデッドロックが続いた場合も、`retry_after` 2秒で返す|自動では再送しない。待ち時間を表示し、利用者の操作で再実行する|
|500|`stageart_persistence_failed`|保存に失敗し、トランザクション全体をROLLBACKした（PR #7 R14-B）。何も変更されていない。詳細はサーバーログのみ|再試行可能とは表示しない|
|500|`stageart_internal_error`|その他のサーバー・DB障害。詳細はサーバーログのみに出力し、応答には含めない|再試行可能とは表示しない|

どの操作がどの公演状態で拒否されるか、およびロックの取得順序は `04-DomainModel/ProductionLifecycle.md`「公演活動の制限」「Production単位のロック規約」を正とする。

上の表のほか、公演参加者の招待の作成・再送では、email_key の鍵が設定されていない場合に500 `stageart_email_key_not_configured` を返す（何も保存しない。応答には鍵の版の名前を含めない）。各APIの個別のエラーは、`ParticipantAPI.md`「実装済みエンドポイント（PR #7 R14-B）」と `ProductionAPI.md` を参照。レート制限の上限と時間枠は `docs/handoffs/PR7-R14B-OperationsRunbook.md` §3。

## 個人宛てのAPIのキャッシュ（PR #7 R14-B）

個人情報を返すAPI、またはログインしている人によって応答が変わるAPIは、Backend（`PersonalResponseCachePolicy`、`rest_post_dispatch`）が `Cache-Control: no-store` を付ける。成功・エラー（401・403・404・409・410・422・429・500、WordPressの `rest_forbidden`・`rest_no_route` を含む）のどちらにも付ける。WordPressの標準のno-cacheのヘッダーやクライアントの設定には依存しない（WordPressの前段のnginxが、`Cache-Control` のない200の応答を `Authorization` にかかわらずキャッシュした事例があるため）。

対象（`stageart/v1` の下）：

- `/me` と、その下のすべて（`/me/participant-invitations` を含む）
- `/productions/{id}/participants`・`/participant-invitations`・`/delegates`・`/participation-requests` と、その下
- `/participants/{id}` と、その下
- `/participant-invitations` と、その下
- `/production-delegates/{id}` と、その下
- `/participation-requests` と、その下

対象のAPIを追加・変更した場合は、この一覧と `PersonalResponseCachePolicy` を合わせて更新する。実環境（nginxなど）でキャッシュされないことの確認手順は、運用手順書（`docs/handoffs/PR7-R14B-OperationsRunbook.md`）を参照。

---

# Authentication

Version 1.0では以下をサポートする。

- Google Login
- Email Login

Accountは認証基盤であり、
公開APIとして提供しない。

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

```
/api/v1/
```

Business RuleはVersionへ依存しない。

---

# Domain Events

APIはBusiness Processを実装しない。

Business ProcessはDomain Eventを契機として開始する。

例）

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
- DomainとAPIは1対1に対応しない。
- APIはBusiness Resourceを公開する。
- ResourceはBusiness Resourceとして設計する。
- 親子関係はURIで表現する。
- 作成・一覧取得は親Resource配下で行う。
- 個別取得・更新・削除は対象Resourceを利用する。
- 公開APIはAggregate Rootのみを公開する。
- 子EntityはAggregate Root経由でのみ操作する。
- ValidationはApplication Layerが担当する。
- Business RuleはDomain Layerが担当する。
- APIはDomain Eventの入口となる。
- ResponseはPresentation用DTOを返却する。
- Domain Entityを直接公開しない。
- Domain Modelを唯一の設計基準とする。
