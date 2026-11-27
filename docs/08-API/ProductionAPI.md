# StageArt Blueprint
# API : Production

Version : 4.2

- 4.2（2026-11-27）：招待と登録の方針の実装（`feature/invitation-simplify`）に合わせ、「ProductionDelegateの有効性」から同意の条件・`CONSENT_UNCONFIRMED`・`target_not_consented` を除いた。

---

# Purpose

Production APIはProductionドメインを操作するためのREST APIを定義する。

ProductionはOrganizationが管理する公開公演を表すBusiness Resourceである。

Production APIはProductionを中心として、
公演に必要な関連情報を集約して提供する。

ProjectはInternal Domainであるため公開APIには含めない。

Productionには、
公演の管理責任者であるPrimaryManagerと、
必要に応じて設定されるProductionDelegateが存在する。

PrimaryManagerおよびProductionDelegateは、
Productionに紐づく情報を誰が更新できるかを決定するために利用する。

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

PrimaryManagerおよびProductionDelegateは
管理権限情報であるため、
一般公開Resourceとして無条件には公開しない。

利用者の権限に応じて管理情報を取得できる。

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
- Production作成者に対応するPersonをPrimaryManagerとして設定する。

PrimaryManagerはProduction作成時に必ず設定する。

PrimaryManagerはProductionに関する全権限を持つ。

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

管理権限を持つ利用者は、
以下の管理情報も取得できる。

- PrimaryManager
- ProductionDelegates
- DelegateRole

Productionのレスポンス（取得・一覧・各状態変更の結果）は、要求者について `can_complete`（boolean）を含む（Version 4.1、2026-10-29）。

- 要求者が公演完了（ACTIVE → COMPLETED）の権限を持ち、かつProductionがACTIVEのときtrueとする。
- 公演完了の権限を持つのはPrimary Managerのみである（2026-11-24確定、PR #8 統合。それまでは会計管理者・代理人も含めていた）。判定は公演完了APIと同じ認可処理による。
- 精算が完了していない等、公演完了時の制約は `can_complete` では判定しない。trueでも、公演完了APIはそれらの制約により拒否しうる。
- 公演開始・アーカイブ・中止等、他の状態変更の権限は `can_complete` に含まれず、従来どおりPrimary Managerのみである。
- 画面は、公演完了の操作の表示を `can_complete` で判定する。

Productionのレスポンスは、要求者について次の値も含む（2026-11-24、PR #8 統合）。いずれも画面の表示制御のためのもので、各APIは操作ごとに同じ規則で認可する。

| 項目 | 意味 |
|---|---|
| `delegate_roles` | 要求者が当該Productionで持つACTIVEな担当Roleのすべて |
| `is_proxy_manager` | 公演代理人（PARTICIPANT_MANAGERとREHEARSAL_MANAGERを同時にACTIVEで持つ） |
| `can_manage_production_operations` | Primary Managerまたは公演代理人（公演情報・公開ページ・公開切替・Slug・定員・公演回の共通設定・上演時間・参加コード） |
| `can_manage_accounting` | Primary Manager、公演代理人または会計担当（予算・経費・実績・精算） |
| `can_manage_production_delegates` | Primary Managerまたは公演代理人（公演代理人・会計担当・受付担当の任命・変更） |

Productionの更新（`PUT /productions/{id}`）は、Primary Managerまたは公演代理人が実行できる（2026-11-24、PR #8 統合。それまではPrimary Managerのみ）。

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

送信項目と未送信項目（PR #7 R14-A、2026-11-14）：

- リクエストに含まれない項目は変更しない（現在の値を保持する）。
- 値に null を指定した項目は削除（空）にする。
- 公開切り替え・メンバー管理・作成画面の「公開する」など、一部の項目だけを送る画面があるため。R14-A以前は未送信項目もnullで上書きしており、説明・会場・日程・クレジット・定員・上演時間が消え、上演時間の変更が公演回の終演時刻にも及んでいた。
- 定員・上演時間を送ったときだけ、各Performanceへの反映（定員の上書き・終演時刻の再計算）を行う。

Productionの更新には、
Productionに対する更新権限が必要である。

PrimaryManagerは更新可能なすべてのProduction情報を変更できる。

ProductionDelegateは、
割り当てられたDelegateRoleに定義された権限の範囲で更新できる。

---

# Change Primary Manager

## Request

PATCH /api/v1/productions/{productionId}/primary-manager

### Request Body

{
  "personId": "person-001"
}

### Business Rules

- ProductionのPrimaryManagerを変更する。
- 新しいPersonをPrimaryManagerとして設定する。
- ProductionPrimaryManagerChangedを発行する。

PrimaryManagerは一つのProductionにつき一人のみ設定する。

PrimaryManagerの変更後、
新しいPrimaryManagerがProductionに関する全権限を持つ。

過去に実行された操作のCreatedByおよびUpdatedByは変更しない。

PrimaryManagerの変更は、
Productionに対する管理権限を持つPrimaryManagerが実行できる。

ProductionDelegateは、
DelegateRoleにPrimaryManager変更権限が明示的に定義されない限り、
PrimaryManagerを変更できない。

---

# List Production Delegates

## Request

GET /api/v1/productions/{productionId}/delegates

### Response

Productionに設定されたProductionDelegate一覧を返却する。

取得可能情報

- ProductionDelegateId
- Person
- DelegateRole
- CreatedAt
- CreatedBy
- UpdatedAt
- UpdatedBy

---

# Add Production Delegate

## Request

POST /api/v1/productions/{productionId}/delegates

### Request Body

{
  "personId": "person-002",
  "delegateRoleId": "rehearsal-manager"
}

### Business Rules

- ProductionDelegateを作成する。
- Productionへ紐付ける。
- Personへ紐付ける。
- DelegateRoleへ紐付ける。
- ProductionDelegateAddedを発行する。

ProductionDelegateは一つのProductionに対して
複数設定できる。

同一Personを複数のProductionのDelegateとして設定できる。

同一Personであっても、
Productionごとに異なるDelegateRoleを設定できる。

---

# Update Production Delegate

## Request

PUT /api/v1/productions/{productionId}/delegates/{productionDelegateId}

更新可能項目

- Person
- DelegateRole

ProductionDelegateIdは変更できない。

Personを変更する場合は、
既存のProductionDelegateを別Personへ差し替える。

DelegateRoleを変更する場合は、
新しいDelegateRoleへ変更する。

変更完了時に、

- UpdatedBy
- UpdatedAt

を更新する。

DelegateRoleの変更によって、
ProductionDelegateが持つ権限も変更される。

ProductionDelegateUpdatedを発行する。

---

# Remove Production Delegate

## Request

DELETE /api/v1/productions/{productionId}/delegates/{productionDelegateId}

### Business Rules

- ProductionDelegateをProductionから削除する。
- ProductionDelegateRemovedを発行する。

削除後、
対象PersonはProduction単位のDelegate権限を失う。

過去に実行された操作のCreatedByおよびUpdatedByは変更しない。

---

# Delegate Role

DelegateRoleは、
ProductionDelegateへ付与する権限セットを表すマスターである。

DelegateRole自体はProductionごとに作成しない。

ProductionDelegateから
あらかじめ定義されたDelegateRoleを参照する。

例）

REHEARSAL_MANAGER
RESERVATION_MANAGER
PARTICIPANT_MANAGER
PERFORMANCE_MANAGER

DelegateRoleが持つ具体的な権限は
Authorization設計で定義する。

---

# Delegate Role Scope

DelegateRoleによる権限は、
Production単位で有効となる。

例えば、

Production A
    ↓
Person A
    ↓
REHEARSAL_MANAGER

と、

Production B
    ↓
Person A
    ↓
RESERVATION_MANAGER

を同時に設定できる。

Person自身のRoleやOrganization Membershipによって
ProductionDelegateのRoleが決定されることはない。

---

# Publish Production

## Request

PATCH /api/v1/productions/{productionId}/publish

### Business Rules

- ProductionStatusをPublishedへ変更する。
- ProductionPublishedを発行する。

公開にはProductionに対する公開権限が必要である。

PrimaryManagerは公開できる。

ProductionDelegateは、
DelegateRoleに公開権限が定義されている場合に公開できる。

---

# Archive Production

## Request

PATCH /api/v1/productions/{productionId}/archive

### Business Rules

- ProductionStatusをArchivedへ変更する。
- ProductionArchivedを発行する。

アーカイブにはProductionに対するアーカイブ権限が必要である。

PrimaryManagerはアーカイブできる。

ProductionDelegateは、
DelegateRoleにアーカイブ権限が定義されている場合にアーカイブできる。

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

PrimaryManagerおよびProductionDelegateは、
一般公開検索の対象としない。

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
    ├── 昼公演
    ├── 夜公演
    └── 別日公演

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

Production APIの認可は、
Organization MembershipとProduction単位の管理権限を
組み合わせて判定する。

---

# Organization Membership

Organization Membershipは、
Organization単位の所属・権限を表す。

Organization Membershipによって、
Organizationに対する操作権限を制御する。

---

# Production Primary Manager Authorization

PrimaryManagerは、
Productionに関する全権限を持つ。

PrimaryManagerは、
DelegateRoleによる制限を受けない。

PrimaryManagerは以下を実行できる。

- Production情報の更新
- Performance等のProduction関連情報の管理
- PrimaryManagerの変更
- ProductionDelegateの追加
- ProductionDelegateの変更
- ProductionDelegateの削除
- DelegateRoleの設定変更
- Productionの公開
- Productionのアーカイブ

各DomainのBusiness Ruleによって
個別に制約される操作は、そのDomainのRuleに従う。

---

# Production Delegate Authorization

ProductionDelegateは、
設定されたDelegateRoleに定義された権限のみを持つ。

DelegateRoleに権限がない操作は実行できない。

ProductionDelegateの権限は
そのProductionに対してのみ有効である。

ProductionDelegateは、
Organization Membershipを変更する権限を持たない。

ProductionDelegateの権限は、
ProductionDelegateが設定されている間のみ有効である。

ProductionDelegateを削除すると、
そのPersonはProduction単位の委任権限を失う。

---

# Management Authorization

Productionに紐づく各Business Resourceの更新可否は、
以下の順序で判定する。

1. PrimaryManager
   ↓
   全権限

2. ProductionDelegate
   ↓
   DelegateRole
   ↓
   定義された権限

3. 権限なし
   ↓
   操作不可

Organization Membershipによる権限と
Production単位の権限は混同しない。

---

# Management Scope

Production単位の管理権限は、
Productionに紐づく情報を更新するために利用する。

将来的に以下の管理へ利用できる。

- Production
- Performance
- Participant
- Reservation
- Rehearsal
- Schedule
- Task
- Document

PrimaryManagerはこれらの管理権限をすべて持つ。

ProductionDelegateは、
DelegateRoleに定義された範囲のみ管理できる。

---

# Audit Information

ProductionDelegateの追加・変更・削除では、
以下の監査情報を記録する。

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

PrimaryManagerの変更についても、
操作を実行した利用者を監査情報として記録する。

過去に実行された操作のCreatedByおよびUpdatedByは、
管理者変更によって書き換えない。

---

# Domain Events

Production APIは以下のDomain Eventを利用する。

- ProductionCreated
- ProductionPublished
- ProductionArchived
- ProductionPrimaryManagerChanged
- ProductionDelegateAdded
- ProductionDelegateUpdated
- ProductionDelegateRemoved

Reservationに関するDomain Eventは
Production APIでは発行しない。

Reservationに関するDomain Eventは
Reservation Domainが発行する。

---

# Production行のロックと公演状態（PR #7 レビュー R5）

実装済みエンドポイント（`/wp-json/stageart/v1/...`）のうち、Production行を書き換える処理と、精算・チケット設定について。

## Production行の排他ロック

以下はトランザクションの最初にProduction行を排他ロック（`FOR UPDATE`）で読み直し、その行に変更を適用して保存する。保存は行全体の書き込みのため、ロック前に読んだコピーを保存して、同時に確定した決算完了・中止・設定変更を元に戻すことはない。

- `PUT /productions/{id}`（基本情報・定員・公演時間。定員・公演時間の各Performanceへの反映も同じトランザクション。R6：定員の一括上書きで、CANCELLED以外のいずれかのPerformanceの定員が予約済み席数を下回る場合は409 `stageart_capacity_below_reservations` で全体を拒否し、何も変更しない）
- `/productions/{id}/activate`、`/complete`、`/cancel`、`/archive`
- `/productions/{id}/primary-manager`
- `/productions/{id}/ticket-sales-settings`
- `/productions/{id}/quota-ticket-back-settings`
- `/tickets/{id}`（価格等の変更）、`/tickets/{id}/archive`：Production行の排他ロックの下でTicketを読み直して変更する

予約・受付は同じ行の共有ロックを取るため、これらの処理とは互いに確定を待ち合わせる。

## 精算と公演状態

| エンドポイント | CANCELLED | COMPLETED / ARCHIVED |
|------|------|------|
| `POST /productions/{id}/settlement/members/{person_id}/settle` | 可 | 409 `stageart_production_closed` |
| `POST /productions/{id}/settlement/members/{person_id}/cancel-settlement` | 可 | 409 `stageart_production_closed`（精算履歴は変更しない） |
| `/productions/{id}/quota-ticket-back-settings`（変更） | 可 | 409 `stageart_production_closed` |

- 判定はProduction行の排他ロック下で読んだStatusで行う。決算完了と精算取消が同時に要求された場合、先に確定した方の結果を後の処理が読む（決算完了が先なら精算取消は409、精算取消が先なら決算完了は未精算額を検出して拒否）。
- 決算完了後の訂正には、公演を明示的に再開する管理フローが必要（未実装）。

## 決算完了と精算の一致（R7）

`PATCH /productions/{id}/complete` は、すべてのメンバーで「精算済み額 ＝ 確定チケットバック額」のときにだけ完了する。

| 状態 | HTTP | code |
|------|------|------|
| 未精算あり（精算済み ＜ 確定） | 422 | `stageart_production_settlement_incomplete`（既存） |
| 払い過ぎ（精算済み ＞ 確定。精算後のCheck-in取消・返金で起きる） | 422 | `stageart_production_settlement_overpaid` |

払い過ぎの応答例：

```json
{
  "code": "stageart_production_settlement_overpaid",
  "message": "This Production cannot be completed: 1 member(s) were settled 300 more than their confirmed Ticket Back.",
  "data": {
    "status": 422,
    "total_overpaid_amount": 300,
    "members": [
      { "person_id": "…", "confirmed_ticket_back_amount": 0, "settled_amount": 300, "overpaid_amount": 300 }
    ]
  }
}
```

`GET /productions/{id}/settlement` の各メンバーには `overpaid_amount`（整数、払い過ぎがなければ0）を追加した。詳細は `04-DomainModel/ProductionLifecycle.md`「決算完了の精算条件」。

## 共通

ロック待ちタイムアウト・デッドロックは503 `stageart_database_busy`（何も保存されていない。再実行可能）、その他のDB障害は500 `stageart_internal_error`（`README.md` 参照）。公演状態ごとの業務ルールとロック順序は `04-DomainModel/ProductionLifecycle.md` を正とする。

---

# ProductionDelegateの有効性（PR #7 R14-B）

実装済みエンドポイント（`/wp-json/stageart/v1/...`）の担当者（ProductionDelegate）について。設計の正本は `docs/handoffs/PR7-R14B-ParticipantInvitationDesign.md` §4.6・§7.1（設計書ブランチ、第9版 `9b42eb5`）。

## 有効な担当者の条件

担当者の行がRoleのPermissionを与えるのは、次の両方を満たす場合だけ。

1. 担当者の行の `status` が ACTIVE。
2. その人物が、この公演の **ACTIVEなPERSONの参加者**である（同意の記録 `consented_at` は条件にしない。2026-11-27、`../03-InvitationAndEnrollmentPolicy.md` §3.3）。

- 条件を満たさないACTIVEな行は、変更せずに残るが、権限を与えない（「無効」）。
- PrimaryManagerの権限は、担当者の行・参加者の状態に依存しない。
- R14-Bより前からある参加者（同意の記録なし）の人物の担当者の行も、2の条件で有効になる（2026-11-27）。

## エンドポイント

| メソッド・パス | 内容 |
|---|---|
| `GET /productions/{id}/delegates` | 担当者の一覧（`effective`・`ineffective_reason` 付き） |
| `GET /productions/{id}/delegates/me` | ログインしている人物の、この公演での担当者の行だけ（第9段階でユーザーの承認を得て追加。設計書 §10「公演の画面（担当者本人）」） |
| `POST /productions/{id}/delegates` | 任命（本文：`person_id`、`role`） |
| `PUT /production-delegates/{id}` | 役割・状態の変更（本文：`role`、`status`） |
| `DELETE /production-delegates/{id}` | 担当者の行の削除 |

一覧の各行：`id`・`production_id`・`person_id`・`person_family_name`・`person_given_name`・`role`・`status`・`created_by`・`created_at`・`updated_by`・`updated_at`・`effective`・`ineffective_reason`。

`GET /productions/{id}/delegates/me` の各行：`id`・`production_id`・`role`・`status`・`effective`・`ineffective_reason`。ログインしていれば誰でも呼べ、本人の行だけを返す（なければ空の配列）。他の人物の情報・氏名・メールアドレスは返さない。公演がなければ404 `stageart_production_not_found`。

`ineffective_reason`（`effective` が `true` なら `null`）：

| 値 | 意味 |
|---|---|
| `INACTIVE` | 担当者の行が INACTIVE |
| `NOT_ACTIVE_PARTICIPANT` | その人物がACTIVEなPERSONの参加者でない |
（`CONSENT_UNCONFIRMED` は 2026-11-27 に廃止した。）

## 任命・変更の条件

- 任命（`POST`）と、`PUT` で行が新たに有効な任命になる場合（INACTIVE → ACTIVE、または役割の変更）は、対象の人物がACTIVEなPERSONの参加者でなければならない。参加者でなければ422 `stageart_production_delegate_target_not_participant`（422 `stageart_production_delegate_target_not_consented` は 2026-11-27 に廃止した）。
- 同じ公演・人物・役割の INACTIVE の行がある場合、`POST` は新しい行を作らず、その行を ACTIVE に戻す（監査ログ `DELEGATE_REACTIVATED`。新しい行は `DELEGATE_APPOINTED`）。すでにACTIVEな行は409 `stageart_production_delegate_already_exists`。
- ACTIVEな行を同じ役割のまま保つ変更は、任命ではないため、条件を確認し直さない（無効なまま残る）。

## 参加者の状態の変更による担当者の無効化

次の操作で、その人物のこの公演の担当者の行を同じトランザクションで INACTIVE にし、監査ログ `DELEGATE_DEACTIVATED` に理由を記録する。

| 操作 | 理由 |
|---|---|
| `PUT /participants/{id}` で ACTIVE → INACTIVE | `PARTICIPANT_DEACTIVATED` |
| `DELETE /participants/{id}`（ACTIVE・INACTIVE → CANCELLED） | `PARTICIPANT_REMOVED` |
| `POST /participants/{id}/withdraw`（本人の取りやめ） | `PARTICIPANT_WITHDRAWN` |
| 招待による登録で新たに ACTIVE になった | `DORMANT_ON_ENROLLMENT`（改めて任命する） |
| `PUT /production-delegates/{id}` で INACTIVE にした | `MANAGER` |

担当者のAPIの応答には `Cache-Control: no-store` を付ける（`README.md`「個人宛てのAPIのキャッシュ」）。

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
- Rehearsal Management
- Schedule Management
- Task Management
- Production-specific Authorization

DelegateRoleは、
将来追加されるDomainに対応して拡張する。

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
- Productionは一人のPrimaryManagerを持つ。
- PrimaryManagerはPersonを参照する。
- PrimaryManagerはProductionに関する全権限を持つ。
- Productionは0人以上のProductionDelegateを持つ。
- ProductionDelegateはPersonを参照する。
- ProductionDelegateはDelegateRoleを参照する。
- DelegateRoleはあらかじめ定義された権限セットを表す。
- DelegateRoleはProduction単位で適用される。
- 同一PersonがProductionごとに異なるDelegateRoleを持つことを許可する。
- Organization MembershipとProduction単位の権限を分離する。
- ProductionDelegateはPerson自身のRoleではない。
- PrimaryManagerにはDelegateRoleを設定しない。
- ProductionDelegateは設定された権限のみ持つ。
- 管理権限情報は一般公開情報ではない。
- ProductionDelegateの追加・変更・削除は監査情報を保持する。
- 管理権限の変更は過去のCreatedByおよびUpdatedByを変更しない。
- Business RuleはDomain Layerが管理する。
- Domain Eventを利用してBusiness Processを開始する。
- APIはRESTを採用する。