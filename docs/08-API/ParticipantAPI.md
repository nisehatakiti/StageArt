# StageArt Blueprint
# API : Participant

Version : 3.1

- 3.1（2026-11-19）：PR #7 R14-B（本人の同意に基づく公演参加者登録）の実装済みエンドポイントを「実装済みエンドポイント（PR #7 R14-B）」に追記した。以下のBlueprintの記述のうち、R14-Bで変わった点（PERSON・ORGANIZATIONの直接追加の廃止、招待・承諾・辞退、参加者情報の開示の制御、本人の参加の取りやめ、認可）は、その節を正とする。

---

# Purpose

Participant APIはParticipantドメインを操作するためのREST APIを定義する。

ParticipantはProductionへ参加する活動主体を表すBusiness Resourceである。

活動主体はSubjectによって表現する。

SubjectはPersonまたはOrganizationを参照する共通Referenceであり、
ParticipantはSubjectを通じて活動主体を管理する。

ParticipantはProductionにおける
「誰が、どのような立場で参加しているか」を表現する。

ParticipantはReservationにおけるHandledParticipantとして
予約の「○○扱い」を表現することができる。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

ParticipantはProduction配下のResourceとして公開する。

/api/v1/productions/{productionId}/participants

Participant固有の操作はParticipant Resourceとして公開する。

/api/v1/participants/{participantId}

---

# Public Resource

Participant APIが公開する情報

- Participant
- Subject
- ParticipantType
- Role
- CreditOrder
- Visibility
- Status

SubjectはPersonまたはOrganizationを表す。

---

# Create Participant

## Request

POST /api/v1/productions/{productionId}/participants

### Request Body

{
  "subject": {
    "type": "PERSON",
    "id": "person-001"
  },
  "participantType": "CAST",
  "role": "主演",
  "creditOrder": 1
}

### Success

201 Created

> R14-B以降、PERSON・ORGANIZATIONのSubjectを指定した直接の作成は422で拒否する。人物の参加は招待の承諾（または参加申請の承認）だけで成立する。管理者が直接作成できるのはNAME_ONLY（氏名だけの参加者）だけ。下記「実装済みエンドポイント（PR #7 R14-B）」を参照。

### Business Rules

- Participantを作成する。
- Productionへ紐付ける。
- Subjectを登録する。
- ParticipantAddedを発行する。

ParticipantAddedは、
ParticipantがProductionへ参加した事実を表現する。

Participantの作成によって、
Reservationを自動生成しない。

Participantの作成によって、
Reservationを自動変更しない。

Historyの生成・更新はParticipant API自身の責務としない。

---

# Get Participant

## Request

GET /api/v1/participants/{participantId}

取得可能情報

- Participant
- Subject
- ParticipantType
- Role
- CreditOrder
- Visibility
- Status

---

# Update Participant

## Request

PUT /api/v1/participants/{participantId}

更新可能項目

- ParticipantType
- Role
- CreditOrder
- Visibility
- Status

Subjectは変更できない。

活動主体を変更する場合は、
Participantを作り直す。

Participantの更新によって、
既存ReservationのHandledParticipantを自動変更しない。

既存ReservationのHandledParticipantを変更する場合は、
ReservationのBusiness Ruleに従ってReservation APIから変更する。

---

# Remove Participant

## Request

DELETE /api/v1/participants/{participantId}

### Business Rules

- ParticipantをProductionから削除する。
- ParticipantRemovedを発行する。

Participantの削除によって、
既存Reservationを自動削除しない。

既存ReservationにHandledParticipantとして
設定されているParticipantを削除する場合は、
Reservationとの整合性をApplication Layerで確認する。

Historyの直接更新は行わない。

---

# List Participants

## Request

GET /api/v1/productions/{productionId}/participants

---

# Search

検索対象

- Subject
- ParticipantType
- Role
- Status

---

# Handled Participant

Participantは、
ReservationにおけるHandledParticipantとして
指定することができる。

HandledParticipantは、
予約における「○○扱い」を表す。

例えば、

Participant
    = 山田

Reservation
    ↓
HandledParticipant
    = 山田

という関係になる。

HandledParticipantは、
BookerおよびCreatedByとは異なる概念である。

Booker
    = 誰の予約か

HandledParticipant
    = 誰扱いの予約か

CreatedBy
    = 誰が予約を入力したか

---

# Participant Reservation Page

Participantは、
自分扱いの予約を受け付けるための
専用予約ページを持つことができる。

この専用予約ページは、
Participant自身が自分扱いの予約を入力・管理するために使用する。

ただし、
予約そのものはReservation Domainが管理する。

Participant APIは、
Participant専用予約ページのUIや
Portalの画面仕様を直接管理しない。

PortalはParticipantを参照し、
Participantに対応する予約受付機能を提供する。

---

# Participant and Reservation

ParticipantとReservationの関係は以下のようになる。

Production
    ↓
Participant
    ↓
HandledParticipant
    ↓
Reservation

Reservationは、
Participantが担当する「○○扱い」の予約を表現できる。

ただしReservationはParticipantの子Resourceではない。

ReservationはPerformanceに所属する独立したAggregateとして管理する。

そのため、

Participant
    ↓
Reservation

という直接的なAggregate関係は持たない。

---

# Reservation Creation by Participant

Participant本人が自分扱いの予約を入力する場合でも、
Reservationの作成はReservation APIのBusiness Ruleに従う。

例えば、

Booker
    = 観客A

HandledParticipant
    = Participant 山田

CreatedBy
    = Participant 山田

というReservationを作成できる。

BookerとHandledParticipantとCreatedByは
それぞれ異なる意味を持つ。

---

# Reservation Creation by Other User

Participant以外の利用者が
Participant扱いの予約を入力することもできる。

例えば、

Booker
    = 観客A

HandledParticipant
    = Participant 山田

CreatedBy
    = 制作スタッフ

というReservationを作成できる。

この場合でも、
Participant APIがCreatedByを管理することはない。

CreatedByはReservation側で管理する。

---

# Reservation Changes

Participantが自分扱いのReservationを管理する場合でも、
Reservationの変更ルールはReservation Domainが管理する。

Check In前であれば、
ReservationのBusiness Ruleに従って変更できる。

Check In後は、
Reservationを変更できない。

Participant APIは、
Reservationの変更を直接実行しない。

---

# Reservation Cancellation

Participantが自分扱いのReservationを管理する場合でも、
ReservationのキャンセルはReservation Domainが管理する。

Check In前であれば、
ReservationのBusiness Ruleに従ってキャンセルできる。

Check In後は、
Reservationをキャンセルできない。

Participant APIは、
Reservationのキャンセルを直接実行しない。

---

# Authorization

Participantの追加・更新・削除は
Organization Membershipによって認可する。

> 実装（R14-B時点）の認可は、ProductionのPrimaryManagerと、有効なProductionDelegate（ProductionDelegateのRoleのPermission）による。下記「実装済みエンドポイント（PR #7 R14-B）」の「認可」を参照。

Roleに応じて利用可能な操作を制御する。

Participant本人による自分扱い予約の操作権限は、
Participant PortalおよびReservation側の認可ルールで管理する。

Participant APIの管理権限と、
Participant専用予約ページの利用権限は分離する。

---

# Domain Events

Participant APIは以下のDomain Eventを利用する。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

Participant Domain Eventは、
Participantに関するBusiness Processを開始する契機として利用する。

Reservationに関するDomain Eventは、
Reservation Domainが発行する。

Reservation Domain Event

- ReservationCreated
- ReservationUpdated
- ReservationCheckedIn
- ReservationCancelled

Participant APIはReservation Domain Eventを発行しない。

---

# History

Participant APIはHistoryを直接更新しない。

Participantに関するHistory処理が必要な場合は、
Participant Domain Eventを契機として
History Domainが処理する。

Participant APIとHistory Domainを直接結合しない。

また、
ReservationのCheck InによるAudience Historyは、
ReservationCheckedInを契機としてHistory Domainが生成する。

Participantの存在だけでは、
Audience Historyを生成しない。

---

# 実装済みエンドポイント（PR #7 R14-B）

実装済みのエンドポイント（`/wp-json/stageart/v1/...`）。すべてログインが必要（未ログインはWordPressの401）。設計の正本は `docs/handoffs/PR7-R14B-ParticipantInvitationDesign.md`（設計書ブランチ `docs/r14b-participant-invitation-design`、第9版 `9b42eb5`）の §4〜§7・§9.2。運用（鍵・レート制限・保存期間）は `docs/handoffs/PR7-R14B-OperationsRunbook.md`。

## 参加者の登録の原則

- 公演の参加者に**人物（PERSON）を直接追加しない**。人物が参加者になるのは、本人が招待を承諾したとき、または本人の参加申請が承認されたときだけ（本人の同意）。
- 招待の作成は、宛先のメールアドレスが登録済みかどうかにかかわらず、同じ処理・同じ応答になる。アドレスから人物を検索しない。登録済みかどうか、人物ID・氏名は応答に含めない。
- 管理者が直接記録できるのは、**NAME_ONLY**（氏名だけの参加者。StageArtの人物に紐付かず、同意の対象外）だけ。
- ORGANIZATIONのSubjectも直接追加しない。

## 認可

| 操作 | 実行できる人 |
|---|---|
| 参加者の一覧（`GET /productions/{id}/participants`） | PrimaryManager、有効な担当者のうち `Participant.Read` を持つ役割（PARTICIPANT_MANAGER・REHEARSAL_MANAGER） |
| 参加者の取得・作成・更新・削除、招待の作成・一覧・再送・取消 | PrimaryManager、有効な担当者のうち `Participant.Update` を持つ役割（PARTICIPANT_MANAGER） |
| 本人の招待の一覧・承諾・辞退、参加の取りやめ（withdraw） | 本人だけ |

- 担当者（ProductionDelegate）の権限は、担当者の行がACTIVEで、かつ本人が同意を確認済みのACTIVEなPERSONの参加者である場合だけ有効になる（`ProductionAPI.md`「ProductionDelegateの有効性（PR #7 R14-B）」）。PrimaryManagerの権限は、これに依存しない。

## エンドポイント一覧

| メソッド・パス | 内容 | 成功 |
|---|---|---|
| `GET /productions/{id}/participants` | 参加者の一覧（開示の制御あり） | 200 |
| `POST /productions/{id}/participants` | NAME_ONLYの参加者の記録 | 201 |
| `GET /participants/{id}` | 参加者の取得（開示の制御あり） | 200 |
| `PUT /participants/{id}` | 役割（`participant_type`）・状態・備考の変更 | 200 |
| `DELETE /participants/{id}` | 管理者による参加の取消（行は削除せず CANCELLED） | 204 |
| `POST /participants/{id}/withdraw` | 本人による参加の取りやめ（WITHDRAWN） | 200 |
| `POST /productions/{id}/participant-invitations` | 招待の作成（同じアドレス・役割の承諾待ちがあれば再送） | 201 |
| `GET /productions/{id}/participant-invitations` | 管理者向けの招待の一覧 | 200 |
| `POST /participant-invitations/{id}/resend` | 招待の再送 | 200 |
| `POST /participant-invitations/{id}/cancel` | 招待の取消 | 200 |
| `GET /me/participant-invitations` | 自分宛ての招待の一覧 | 200 |
| `POST /participant-invitations/{id}/accept` | 自分宛ての招待の承諾 | 200 |
| `POST /participant-invitations/{id}/reject` | 自分宛ての招待の辞退 | 200 |

- 未登録のまま招待の内容をトークンで照会するAPI（`GET /participant-invitations/resolve?token=`）は削除した（WordPressの404 `rest_no_route`）。招待をトークンで照会・承諾するAPIはない。
- 上記のすべての応答（成功・エラーとも）に `Cache-Control: no-store` を付ける（`README.md`「個人宛てのAPIのキャッシュ」）。

## 参加者（Participant）の応答

```json
{
  "id": "…", "production_id": "…",
  "subject_type": "PERSON", "subject_id": "…",
  "participant_type": "CAST", "status": "ACTIVE",
  "created_at": "…", "updated_at": "…",
  "remarks": "…", "display_name": "…",
  "person_family_name": "…", "person_given_name": "…", "email": "…",
  "enrollment_source": "INVITATION", "consent_status": "CONFIRMED", "consented_at": "…"
}
```

| 項目 | 値 |
|---|---|
| `subject_type` | `PERSON`・`ORGANIZATION`・`NAME_ONLY` |
| `participant_type` | `CAST`・`STAFF` |
| `status` | `DRAFT`・`PENDING`・`ACTIVE`・`INACTIVE`・`CANCELLED`・`REJECTED`・`WITHDRAWN`（`04-DomainModel/Participant.md`） |
| `enrollment_source` | 行が最初に作られた経路。`INVITATION`・`JOIN_REQUEST`・`MANAGER_RECORD`（NAME_ONLY）。R14-Bより前の行は `null` |
| `consent_status` | `CONFIRMED`（同意を記録済み）・`UNCONFIRMED`（PERSONで同意の記録なし。R14-Bより前の行を含む）・`NOT_APPLICABLE`（NAME_ONLY・ORGANIZATION） |
| `consented_at` | 最新の同意の日時。なければ `null` |

開示しない項目は `null` で返す（項目自体は省略しない）。

## 参加者情報の開示（設計書 §7.3）

見る人の区分：

- **PrimaryManager**
- **管理者**：有効なPARTICIPANT_MANAGER（`Participant.Update`）
- **閲覧者**：有効なREHEARSAL_MANAGER（`Participant.Read` だけ。稽古の対象の選定に使う）

公演が PLANNING・ACTIVE のとき：

| 項目 | PrimaryManager・管理者 | 閲覧者 |
|---|---|---|
| `display_name`・`status` など行の基本の項目 | 返す | 返す |
| 姓・名（`person_family_name`・`person_given_name`） | ACTIVE・INACTIVEの行だけ | ACTIVE・INACTIVEの行だけ |
| `email` | ACTIVE・INACTIVEの行だけ | 返さない |
| `enrollment_source`・`consent_status`・`consented_at` | 返す | 返さない |
| CANCELLED・WITHDRAWN・REJECTED（終了した行） | `display_name` と `status` だけ（`subject_id`・`remarks` も `null`） | **行を返さない** |

公演が COMPLETED・ARCHIVED・CANCELLED のとき：上の表と同じ。ただし、閲覧者には姓・名も返さず、`email` は PrimaryManager にだけ返す。同意の項目と終了した行の扱いは変わらない。

- PENDING・DRAFTの行は、ACTIVE・INACTIVEでないため、姓・名・`email` を返さない（行・`display_name`・`status`、管理者には同意の項目を返す）。
- **本人の行**（PERSONで、Subjectが見ている本人）は、見る人の区分・公演の状態にかかわらず、すべての項目を返す。
- **`subject_id` が `null` になる場合**：終了した行（CANCELLED・WITHDRAWN・REJECTED）を本人以外が見るとき。NAME_ONLYの行は、もともと人物IDを持たない。
- `GET /participants/{id}` と `PUT` の応答も、一覧と同じ開示になる。

## NAME_ONLYの記録（`POST /productions/{id}/participants`）

本文：`subject_type`（`NAME_ONLY` だけ受け付ける）、`participant_type`、`display_name`（必須）、`remarks`（任意）。

| HTTP | code | 条件 |
|---|---|---|
| 422 | `stageart_participant_requires_invitation` | `subject_type` が `PERSON`・`ORGANIZATION`（招待を使う） |
| 422 | `stageart_participant_subject_not_eligible` | `display_name` が空 |
| 422 | `stageart_participant_invalid` | 不正な `subject_type`・`participant_type` |
| 403 | `stageart_participant_access_denied` | 権限なし |
| 404 | `stageart_production_not_found` | 公演がない |

作成した行は ACTIVE、`enrollment_source` は `MANAGER_RECORD`。監査ログ `NAME_ONLY_ADDED` を記録する。

## 参加者の変更（`PUT /participants/{id}`）

本文：`participant_type`（必須）、`status`（任意）、`remarks`（任意）。

- `status` を送らない場合は変わらない。現在と同じ値は成功（何も変えない）。
- 状態の変更として受け付けるのは **ACTIVE → INACTIVE だけ**。同じトランザクションで、その人物のこの公演の担当者の行をすべて INACTIVE にする（理由 `PARTICIPANT_DEACTIVATED`）。
- **INACTIVE → ACTIVE は422 `stageart_participant_status_requires_consent`**（復帰は、本人の招待の承諾または参加申請の承認による）。その他の変更は422 `stageart_participant_status_change_not_allowed`。
- 同じSubjectが同じ公演で別の役割の行を持っている場合、その役割への変更は409 `stageart_participant_type_conflict`。
- `remarks`：送らない場合は保持、`null`・空文字は削除、文字列は前後の空白を除いて保存。
- 入力の誤り（`participant_type` がない・不正、`status` が文字列でない・不正など）は422 `stageart_participant_invalid` で、何も変更しない。
- 監査ログ：`DEACTIVATED`・`TYPE_CHANGED`、担当者の無効化は `DELEGATE_DEACTIVATED`。

## 参加の取消（`DELETE /participants/{id}`）

- ACTIVE・INACTIVE → CANCELLED（204）。行・出欠・精算・会計の記録は残す。同じトランザクションで、その人物のこの公演のACTIVEな担当者の行を INACTIVE にする（理由 `PARTICIPANT_REMOVED`）。監査ログ `REMOVED_BY_MANAGER`。
- CANCELLED は成功（何も変えない）。
- PENDING・DRAFT・REJECTED・WITHDRAWN は409 `stageart_participant_cancel_not_allowed`。

## 本人による参加の取りやめ（`POST /participants/{id}/withdraw`）

- 本人のPERSONの行だけ。他人の行・NAME_ONLY・ORGANIZATIONの行は403 `stageart_participant_access_denied`、行がなければ404 `stageart_participant_not_found`。
- ACTIVE → WITHDRAWN。応答は `{ "participant_id", "production_id", "status": "WITHDRAWN" }`。すでに WITHDRAWN なら同じ応答（何も変えない）。
- ACTIVE以外の行は409 `stageart_participant_withdraw_not_allowed`。
- **PrimaryManagerは取りやめられない**：409 `stageart_participant_withdraw_primary_manager`（先に管理者を引き継ぐ）。
- 公演の状態による制限はない。
- 同じトランザクションで、その人物のこの公演の担当者の行をすべて INACTIVE にする（理由 `PARTICIPANT_WITHDRAWN`）。監査ログ `WITHDRAWN`・`DELEGATE_DEACTIVATED`。出欠・精算・会計の記録は変更しない。確定の後、PrimaryManagerと有効なPARTICIPANT_MANAGERに通知する。

## 招待の作成・再送・取消（管理者）

**作成**（`POST /productions/{id}/participant-invitations`）。本文：`name`、`email`、`participant_type`、`remarks`（任意）。

- 応答（201）：`{ "outcome": "INVITATION_SENT" | "INVITATION_RESENT", "invitation": { … } }`。同じ公演・アドレス・役割の期限内の承諾待ちの招待があれば、新しく作らずに再送する（`INVITATION_RESENT`）。
- **公演招待**の有効期限は、作成から7日。再送すると、再送の時点から7日に延びる。R14-Bより前に作られた公演招待は、作成時の期限（24時間）のまま変えない。団体招待（有効期限24時間）は別の機能で、この節の対象外。
- アドレスは前後の空白を除き、小文字にして比較する。
- メールは確定の後に送る。メールにはトークンを含めず、招待の一覧（`/participant-invitations`）へのリンクを載せる。送信の失敗は招待を取り消さず、送信の回数も戻さない。

**再送**（`POST /participant-invitations/{id}/resend`）・**取消**（`POST /participant-invitations/{id}/cancel`）：応答は招待（200）。取消は承諾待ちの招待を CANCELLED（close_reason `MANAGER`）にする。

**送信の上限**（レート制限とは別に、常に適用）：

- 招待1件あたりの再送は5回まで。
- 同じ公演・同じアドレスへの送信（作成・再送の合計）は30日間に6回まで。
- 同じ公演の招待を辞退した人には、辞退から30日間、新しい招待を作れない（役割を問わない）。

| HTTP | code | 条件 |
|---|---|---|
| 409 | `stageart_participant_invitation_production_closed` | 公演が COMPLETED・ARCHIVED・CANCELLED |
| 409 | `stageart_participant_invitation_inviter_revoked` | 再送で、招待した人に権限がなくなっている |
| 409 | `stageart_participant_invitation_recently_rejected` | 辞退から30日以内（`data.available_at`） |
| 429 | `stageart_participant_invitation_resend_limit` | この招待の再送が5回に達した |
| 429 | `stageart_participant_invitation_send_limit` | 30日間の送信が6回に達した（`data.available_at`） |
| 429 | `stageart_rate_limited` | 操作のレート制限（`README.md`） |
| 422 | `stageart_participant_invitation_invalid` | 入力の誤り（作成） |
| 422 | `stageart_participant_invitation_not_pending` | 承諾待ちでない招待の再送 |
| 409 | `stageart_participant_invitation_not_pending` | 承諾待ちでない招待の取消 |
| 403 | `stageart_participant_invitation_access_denied` | 権限なし |
| 404 | `stageart_participant_invitation_not_found`・`stageart_production_not_found` | 招待・公演がない |
| 500 | `stageart_email_key_not_configured` | email_key の鍵が設定されていない（作成・再送。何も保存しない） |

**管理者向けの一覧**（`GET /productions/{id}/participant-invitations`）の各行は、招待の項目（`id`・`production_id`・`email`・`name`・`invited_by_person_id`・`participant_type`・`remarks`・`status`・`created_at`・`expires_at`・`consumed_at`・`is_expired`）に、次を加える。招待された人の人物ID・氏名は含めない。

| 項目 | 内容 |
|---|---|
| `effective_status` | 表示用の状態。承諾待ちは、期限切れ → 公演の終了 → 招待者の権限なし の順に判定して `EXPIRED`・`PRODUCTION_CLOSED`・`INVITER_REVOKED`・`PENDING`。CANCELLED（理由 `INVITER_REVOKED`）は `INVITER_REVOKED`、その他のCANCELLEDは `CANCELLED`。`ACCEPTED`・`REJECTED`・`CONSUMED` はそのまま |
| `responded_at`・`close_reason`・`closed_at` | 保存されている値 |
| `resend_count`・`resend_remaining` | この招待の再送の回数と、5回までの残り |
| `send_status`・`last_sent_at` | 最新の送信の状態（`PENDING`・`SENT`・`FAILED`・`UNKNOWN`）と日時。R14-Bより前の招待は `null` |
| `send_limit` | `{ sent_in_30_days, remaining_in_30_days, available_at }`。期間内に使われた鍵の版が設定されていないなど、数えられない場合は `null` |
| `reinvite_available_at` | REJECTEDの行だけ。最新の辞退から30日後（過ぎていても返す） |

`send_status` の `UNKNOWN` は保存せず、送信を受け付けてから10分たっても結果が記録されていない `PENDING` を表示上そう扱う。`SENT` は送信を受け付けたことだけを表し、相手に届いたことは保証しない。

## 本人の招待の一覧・承諾・辞退

**一覧**（`GET /me/participant-invitations`）：ログインしている人物の**確認済みのアドレス**（ログインのアドレスと、確認済みの通知用アドレス）宛ての招待。トークンは使わない。

- 載せるもの：期限内の承諾待ち（`effective_status` が `PENDING` なら承諾・辞退できる。`PRODUCTION_CLOSED`・`INVITER_REVOKED` は辞退だけ）、期限内の CANCELLED（`PRODUCTION_CLOSED`・`INVITER_REVOKED`、辞退だけ）。期限切れ・回答済み・管理者が取り消したものは載せない。
- 各項目：`id`・`production_id`・`production_name`・`organization_name`・`participant_type`・`invited_by_display_name`（公演での招待者の表示名。なければ「公演の管理者」）・`expires_at`・`effective_status`・`already_participant`・`can_accept`・`can_reject`・`unavailable_reason`。招待のアドレス、管理者が入力した氏名・備考、他の人物の情報は含めない。
- 一覧の取得は何も書き換えない。

**承諾**（`POST /participant-invitations/{id}/accept`）：応答は `{ "participant_id", "production_id", "status" }`。

- 同じ公演・人物・役割の行がなければ作り、あれば再利用して ACTIVE にし、同意（`consent_source` `INVITATION`・`consented_at`）を記録する。すでに ACTIVE の行には同意だけを記録する（「同意の確認を依頼」）。
- 承諾によって新しく ACTIVE になった場合は、その人物のこの公演のACTIVEな担当者の行を INACTIVE にする（理由 `DORMANT_ON_ENROLLMENT`。改めて任命する）。すでにACTIVEで同意だけを記録した場合は、担当者の行は変えない。
- すでに本人が承諾済みなら、同じ応答を返す（何も変えない）。
- 監査ログ：`ACCEPTED`（すでにACTIVEなら `CONSENT_RECORDED` も）。確定の後、招待した人に通知する。

**辞退**（`POST /participant-invitations/{id}/reject`）：応答は `{ "id", "status": "REJECTED", "close_reason" }`。参加者の行は作らず、変更もしない。監査ログ `REJECTED`。招待した人（権限がなくなっていればPrimaryManager）に通知する。

| HTTP | code | 条件（承諾・辞退） |
|---|---|---|
| 403 | `stageart_participant_invitation_forbidden` | 招待に回答できないアカウント（人物・有効なアカウント・確認済みのアドレスがない） |
| 404 | `stageart_participant_invitation_not_found` | 招待がない、または本人宛てでない（区別しない） |
| 409 | `stageart_participant_invitation_not_pending` | 回答済み・管理者が取り消した招待 |
| 409 | `stageart_participant_invitation_closed` | 公演の終了・招待者の権限なしで承諾できない（`data.close_reason`）。辞退はできる |
| 409 | `stageart_participant_draft_exists` | 承諾：同じ役割の DRAFT の行がある（管理者に連絡する） |
| 410 | `stageart_participant_invitation_expired` | 期限切れ |
| 429 | `stageart_rate_limited` | 承諾・辞退の失敗（403・404）が10分間に10回に達した |

一覧の取得で回答できないアカウントの場合も403 `stageart_participant_invitation_forbidden`。

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

- 日替わり出演
- 出演期間
- 出演回指定
- グループ出演
- ゲスト出演
- SNSリンク

Subjectの種類が追加されても、
Participant APIは変更しない。

---

# Design Principles

- ParticipantはProductionへの参加を表すBusiness Resourceである。
- ParticipantはSubjectを通じて活動主体を参照する。
- SubjectはPersonまたはOrganizationを表す。
- ParticipantはPersonおよびOrganizationへ直接依存しない。
- ParticipantTypeはシステムが管理する参加区分である。
- Roleは表示用の役割である。
- Subjectは変更できない。
- ParticipantはReservationのHandledParticipantとして指定できる。
- HandledParticipantは予約の「○○扱い」を表す。
- BookerとHandledParticipantを分離する。
- HandledParticipantとCreatedByを分離する。
- Participant専用予約ページはPortal側で提供する。
- ReservationはReservation Domainが管理する。
- Participant APIはReservationを直接管理しない。
- Participantの変更によって既存Reservationを自動変更しない。
- Participantの削除によって既存Reservationを自動削除しない。
- Reservationの変更はReservation APIが管理する。
- ReservationのキャンセルはReservation APIが管理する。
- Check In済みReservationは変更・キャンセルできない。
- Participant APIはHistoryを直接更新しない。
- ReservationCheckedInによるAudience HistoryはHistory Domainが管理する。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。