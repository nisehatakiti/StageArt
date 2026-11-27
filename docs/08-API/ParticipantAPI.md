# StageArt Blueprint
# API : Participant

Version : 3.3

- 3.3（2026-11-27）：招待と登録の方針の実装（`feature/invitation-simplify`）に合わせ、「実装済みエンドポイント」を書き直した。確認済みのアカウントへの招待時の登録（`MEMBER_ADDED`）、メールアドレスの確認・Googleでのログインの時点の登録、招待の画面のAPI（`GET /me/participant-invitations/{id}`）を記載し、承諾・辞退・自分宛ての一覧のAPI、辞退後30日の制限、`reinvite_available_at` を削除した。担当者の権限は同意の記録を条件にしない。
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

> R14-B以降、PERSON・ORGANIZATIONのSubjectを指定した直接の作成は422で拒否する。人物の参加は招待による登録（2026-11-27 以降、承諾はない）または参加申請の承認だけで成立する。管理者が直接作成できるのはNAME_ONLY（氏名だけの参加者）だけ。下記「実装済みエンドポイント（PR #7 R14-B）」を参照。

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

# 実装済みエンドポイント（PR #7 R14-B、招待と登録の方針 2026-11-27）

招待と登録の方針は `docs/03-InvitationAndEnrollmentPolicy.md` を正本とする。本人による招待の承諾・辞退はない（2026-11-27 に廃止。`feature/invitation-simplify`）。以前の承諾・辞退の仕様は、`docs/handoffs/PR7-R14B-ParticipantInvitationDesign.md`（第9版）に履歴として残る。

実装済みのエンドポイント（`/wp-json/stageart/v1/...`）。すべてログインが必要（未ログインはWordPressの401）。

## 参加者の登録の原則

- 管理者は、本人の参加意思を事前に（StageArtの外で）確認したうえで、メールアドレスを登録して招待する。
- 招待先のメールアドレスが**確認済みのアカウント**のものなら、招待の時点で参加者として登録する（招待メールは送らない）。
- それ以外のアドレスには招待を保存してメールを送る。本人がそのアドレスで登録してメールアドレスの確認を済ませた時点、またはそのアドレス（Googleが確認済みのもの）でGoogleにログインした時点で、参加者として登録する。
- 本人による承諾・辞退、参加意思の再確認はない。登録しても、本人の同意（`consent_source`・`consented_at`）は記録しない。記録するのは登録の経路（`enrollment_source` `INVITATION`）だけ。
- 管理者が人物IDを指定して直接追加する方式はない（`POST /productions/{id}/participants` で PERSON・ORGANIZATION は422）。
- 管理者が直接記録できるのは、**NAME_ONLY**（氏名だけの参加者。StageArtの人物に紐付かない）だけ。

## 認可

| 操作 | 実行できる人 |
|---|---|
| 参加者の一覧（`GET /productions/{id}/participants`） | PrimaryManager、有効な担当者のうち `Participant.Read` を持つ役割（PARTICIPANT_MANAGER・REHEARSAL_MANAGER） |
| 参加者の取得・作成・更新・削除、招待の作成・一覧・再送・取消 | PrimaryManager、有効な担当者のうち `Participant.Update` を持つ役割（PARTICIPANT_MANAGER） |
| 招待メールのリンクから招待を開く（`GET /me/participant-invitations/{id}`）、参加の取りやめ（withdraw） | 本人だけ |

- 担当者（ProductionDelegate）の権限は、担当者の行がACTIVEで、かつその人物がこの公演のACTIVEなPERSONの参加者である場合に有効になる。同意の記録は条件にしない（2026-11-27、ユーザーの判断）。R14-Bより前の参加者（同意の記録なし）も同じ。

## エンドポイント一覧

| メソッド・パス | 内容 | 成功 |
|---|---|---|
| `GET /productions/{id}/participants` | 参加者の一覧（開示の制御あり） | 200 |
| `POST /productions/{id}/participants` | NAME_ONLYの参加者の記録 | 201 |
| `GET /participants/{id}` | 参加者の取得（開示の制御あり） | 200 |
| `PUT /participants/{id}` | 役割（`participant_type`）・状態・備考の変更 | 200 |
| `DELETE /participants/{id}` | 管理者による参加の取消（行は削除せず CANCELLED） | 204 |
| `POST /participants/{id}/withdraw` | 本人による参加の取りやめ（WITHDRAWN） | 200 |
| `POST /productions/{id}/participant-invitations` | 招待（確認済みのアカウントはその場で登録、同じアドレス・役割の登録待ちがあれば再送） | 201 |
| `GET /productions/{id}/participant-invitations` | 管理者向けの招待の一覧 | 200 |
| `POST /participant-invitations/{id}/resend` | 招待の再送 | 200 |
| `POST /participant-invitations/{id}/cancel` | 招待の取消 | 200 |
| `GET /me/participant-invitations/{id}` | 招待メールのリンクから開いた招待（本人宛てだけ） | 200 |

- 2026-11-27 に削除したAPI（WordPressの404 `rest_no_route`）：`GET /me/participant-invitations`（自分宛ての一覧）、`POST /participant-invitations/{id}/accept`（承諾）、`POST /participant-invitations/{id}/reject`（辞退）。
- 未登録のまま招待の内容をトークンで照会するAPI（`GET /participant-invitations/resolve?token=`）はない（R14-Bで削除）。
- 上記のすべての応答（成功・エラーとも）に `Cache-Control: no-store` を付ける（`README.md`「個人宛てのAPIのキャッシュ」）。
- 団体の招待の同じ画面のAPI（`GET /me/organization-invitations/{id}`）は `docs/03-InvitationAndEnrollmentPolicy.md` §3.2。

## 参加者（Participant）の応答

```json
{
  "id": "…", "production_id": "…",
  "subject_type": "PERSON", "subject_id": "…",
  "participant_type": "CAST", "status": "ACTIVE",
  "created_at": "…", "updated_at": "…",
  "remarks": "…", "display_name": "…",
  "person_family_name": "…", "person_given_name": "…", "email": "…",
  "enrollment_source": "INVITATION", "consent_status": "UNCONFIRMED", "consented_at": null
}
```

| 項目 | 値 |
|---|---|
| `subject_type` | `PERSON`・`ORGANIZATION`・`NAME_ONLY` |
| `participant_type` | `CAST`・`STAFF` |
| `status` | `DRAFT`・`PENDING`・`ACTIVE`・`INACTIVE`・`CANCELLED`・`REJECTED`・`WITHDRAWN`（`04-DomainModel/Participant.md`） |
| `enrollment_source` | 行が最初に作られた経路。`INVITATION`・`JOIN_REQUEST`・`MANAGER_RECORD`（NAME_ONLY）。R14-Bより前の行は `null` |
| `consent_status` | 同意の記録の有無（履歴）。`CONFIRMED`（記録あり：R14-Bの承諾、参加申請の承認）・`UNCONFIRMED`（PERSONで記録なし。招待から登録した行、R14-Bより前の行）・`NOT_APPLICABLE`（NAME_ONLY・ORGANIZATION）。権限の判定には使わない。画面には表示しない |
| `consented_at` | 最新の同意の記録の日時。なければ `null` |

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
- **INACTIVE → ACTIVE は422 `stageart_participant_status_requires_consent`**（コードの名前は R14-B のまま）。復帰は、招待による登録または参加申請の承認による。その他の変更は422 `stageart_participant_status_change_not_allowed`。
- 同じSubjectが同じ公演で別の役割の行を持っている場合、その役割への変更は409 `stageart_participant_type_conflict`。
- `remarks`：送らない場合は保持、`null`・空文字は削除、文字列は前後の空白を除いて保存。
- 入力の誤り（`participant_type` がない・不正、`status` が文字列でない・不正など）は422 `stageart_participant_invalid` で、何も変更しない。
- 監査ログ：`DEACTIVATED`・`TYPE_CHANGED`、担当者の無効化は `DELEGATE_DEACTIVATED`。

## 参加の取消（`DELETE /participants/{id}`）

- ACTIVE・INACTIVE → CANCELLED（204）。行・出欠・精算・会計の記録は残す。同じトランザクションで、その人物のこの公演のACTIVEな担当者の行を INACTIVE にする（理由 `PARTICIPANT_REMOVED`）。監査ログ `REMOVED_BY_MANAGER`・`DELEGATE_DEACTIVATED`。
- CANCELLED は成功（何も変えない）。
- PENDING・DRAFT・REJECTED・WITHDRAWN は409 `stageart_participant_cancel_not_allowed`。

## 本人による参加の取りやめ（`POST /participants/{id}/withdraw`）

- 本人のPERSONの行だけ。他人の行・NAME_ONLY・ORGANIZATIONの行は403 `stageart_participant_access_denied`、行がなければ404 `stageart_participant_not_found`。
- ACTIVE → WITHDRAWN。応答は `{ "participant_id", "production_id", "status": "WITHDRAWN" }`。すでに WITHDRAWN なら同じ応答（何も変えない）。
- ACTIVE以外の行は409 `stageart_participant_withdraw_not_allowed`。
- **PrimaryManagerは取りやめられない**：409 `stageart_participant_withdraw_primary_manager`（先に管理者を引き継ぐ）。
- 公演の状態による制限はない。
- 同じトランザクションで、その人物のこの公演の担当者の行をすべて INACTIVE にする（理由 `PARTICIPANT_WITHDRAWN`）。監査ログ `WITHDRAWN`・`DELEGATE_DEACTIVATED`。出欠・精算・会計の記録は変更しない。確定の後、PrimaryManagerと有効なPARTICIPANT_MANAGERに通知する（本人を除く）。

## 招待の作成・再送・取消（管理者）

**作成**（`POST /productions/{id}/participant-invitations`）。本文：`name`、`email`、`participant_type`、`remarks`（任意）。応答は201で、次のどれか。

| `outcome` | 応答 | 条件 |
|---|---|---|
| `MEMBER_ADDED` | `{ "outcome": "MEMBER_ADDED", "participant_id": "…" }` | アドレスが確認済みのアカウントのもの（ログインの確認済みのアドレス、または確認済みの通知用アドレス）。その場で参加者として登録し、招待メールは送らない。同じアドレス・役割の期限内の登録待ちの招待は、完了（`CONSUMED`）にする |
| `INVITATION_SENT` | `{ "outcome": "INVITATION_SENT", "invitation": { … } }` | 上以外。新しい招待を作ってメールを送る |
| `INVITATION_RESENT` | `{ "outcome": "INVITATION_RESENT", "invitation": { … } }` | 同じ公演・アドレス・役割の期限内の登録待ちの招待があり、それを再送した |

- 確認済みのアカウントの登録：同じ公演・人物・役割の行がなければ ACTIVE で作り（`enrollment_source` `INVITATION`、同意は記録しない、`display_name` は管理者が入力した氏名）、CANCELLED・WITHDRAWN・REJECTED・INACTIVE・PENDING の行があれば同じ行を ACTIVE に戻す（`enrollment_source` と過去の同意の記録はそのまま）。新しく参加者になった場合は、その人物のこの公演のACTIVEな担当者の行を INACTIVE にする（理由 `DORMANT_ON_ENROLLMENT`。改めて任命する）。監査ログ `INVITATION_ENROLLED`（`detail.trigger` `INVITATION_CREATED`）。
- アドレスが複数の人物の確認済みのアドレスになっている場合は、確認済みのアカウントとして扱わず、招待を送る。
- **公演招待**の有効期限は、作成から7日。再送すると、再送の時点から7日に延びる。R14-Bより前に作られた公演招待は、作成時の期限（24時間）のまま変えない。団体招待の有効期限は24時間。
- アドレスは前後の空白を除き、小文字にして比較する。
- メールは確定の後に送る。メールにはトークンを含めず、その招待の画面（`/participant-invitations/{招待ID}`）へのリンクを載せる。送信の失敗は招待を取り消さず、送信の回数も戻さない。

**招待からの登録（メールアドレスの確認・Googleでのログイン）**：本人が招待されたアドレスで登録してメールアドレスを確認した時点（`POST /auth/email/verify`）、またはGoogleが確認済みのそのアドレスでGoogleにログインした時点（`POST /auth/google`）に、そのアドレス宛ての期限内の登録待ちの招待で、参加者として登録する（招待ごとに1つのトランザクション）。

- 招待は `CONSUMED`（`consumed_at`、登録した人物 `invitee_person_id`、参加者の行 `participant_id`）になる。`responded_at` は記録しない。監査ログ `INVITATION_ENROLLED`（`detail.trigger` `ADDRESS_VERIFIED`）。
- 行の作り方・再利用・担当者の行の扱いは、作成のときと同じ。すでに同じ役割で ACTIVE なら行は変えず、招待だけを完了にする。
- 公演が COMPLETED・ARCHIVED・CANCELLED、または招待した人に権限がなくなっていれば、登録せず、招待を CANCELLED（close_reason `PRODUCTION_CLOSED`・`INVITER_REVOKED`）にする。`INVITER_REVOKED` のときは、確定の後に PrimaryManager へ1回だけ通知する。
- 同じ役割の DRAFT の行があれば、何も変えず、招待は登録待ちのまま残る（ログに記録）。
- 取消済み・期限切れの招待、別のアドレス宛ての招待、ほかの人物の確認済みのアドレス宛ての招待では、登録しない。同じ招待を何度処理しても、登録は1回だけ。
- 失敗しても、メールアドレスの確認やログインの応答は変えない（ログにIDだけを記録する）。

**再送**（`POST /participant-invitations/{id}/resend`）・**取消**（`POST /participant-invitations/{id}/cancel`）：応答は招待（200）。取消は登録待ちの招待を CANCELLED（close_reason `MANAGER`）にする。

**送信の上限**（レート制限とは別に、常に適用）：

- 招待1件あたりの再送は5回まで。
- 同じ公演・同じアドレスへの送信（作成・再送の合計）は30日間に6回まで。
- 辞退後30日の再招待の制限は、辞退とともに廃止した（2026-11-27）。

| HTTP | code | 条件 |
|---|---|---|
| 409 | `stageart_participant_invitation_production_closed` | 公演が COMPLETED・ARCHIVED・CANCELLED |
| 409 | `stageart_participant_invitation_inviter_revoked` | 再送で、招待した人に権限がなくなっている |
| 409 | `stageart_participant_already_exists` | 作成で、確認済みのアカウントが同じ役割で既にACTIVEな参加者（何も変えない） |
| 409 | `stageart_participant_draft_exists` | 作成で、確認済みのアカウントに同じ役割の DRAFT の行がある（何も変えない） |
| 429 | `stageart_participant_invitation_resend_limit` | この招待の再送が5回に達した |
| 429 | `stageart_participant_invitation_send_limit` | 30日間の送信が6回に達した（`data.available_at`） |
| 429 | `stageart_rate_limited` | 操作のレート制限（`README.md`） |
| 422 | `stageart_participant_invitation_invalid` | 入力の誤り（作成） |
| 422 | `stageart_participant_invitation_not_pending` | 登録待ちでない招待の再送 |
| 409 | `stageart_participant_invitation_not_pending` | 登録待ちでない招待の取消 |
| 403 | `stageart_participant_invitation_access_denied` | 権限なし |
| 404 | `stageart_participant_invitation_not_found`・`stageart_production_not_found` | 招待・公演がない |
| 500 | `stageart_email_key_not_configured` | email_key の鍵が設定されていない（招待の作成・再送。何も保存しない） |

**管理者向けの一覧**（`GET /productions/{id}/participant-invitations`）の各行は、招待の項目（`id`・`production_id`・`email`・`name`・`invited_by_person_id`・`participant_type`・`remarks`・`status`・`created_at`・`expires_at`・`consumed_at`・`is_expired`）に、次の項目を加えたもの。

| 項目 | 内容 |
|---|---|
| `effective_status` | 表示用の状態。登録待ちは、期限切れ → 公演の終了 → 招待者の権限なし の順に判定して `EXPIRED`・`PRODUCTION_CLOSED`・`INVITER_REVOKED`・`PENDING`。CANCELLED（理由 `INVITER_REVOKED`）は `INVITER_REVOKED`、そのほかの CANCELLED は `CANCELLED`。`CONSUMED`（登録済み）、過去の `ACCEPTED`・`REJECTED` はそのまま |
| `responded_at`・`close_reason`・`closed_at` | 保存されている値（`responded_at` は過去の承諾・辞退の行だけ） |
| `resend_count`・`resend_remaining` | この招待の再送の回数と、5回までの残り |
| `send_status`・`last_sent_at` | 最新の送信の状態（`PENDING`・`SENT`・`FAILED`・`UNKNOWN`）と日時。R14-Bより前の招待は `null` |
| `send_limit` | `{ sent_in_30_days, remaining_in_30_days, available_at }`。期間内に使われた鍵の版が設定されていないなど、数えられない場合は `null` |

`reinvite_available_at` は、辞退後30日の制限とともに削除した（2026-11-27）。`send_status` の `UNKNOWN` は保存せず、送信を受け付けてから10分たっても結果が記録されていない `PENDING` を表示上そう扱う。`SENT` は送信を受け付けたことだけを表し、相手に届いたことは保証しない。

## 招待メールのリンクから開いた招待（`GET /me/participant-invitations/{id}`）

招待メールのリンク（`/participant-invitations/{招待ID}`）の画面が使う。招待IDだけでは何も見せない：招待先のアドレスが、ログインしている人物の**確認済みのアドレス**（ログインのアドレスと、確認済みの通知用アドレス）の1つで、そのアドレスがその人物のものと確かめられた場合だけ返す。読み取りだけで、登録・通知・書き込みはしない（開いたことは承諾ではない）。

応答（200）：

```json
{ "id": "…", "production_id": "…", "production_name": "…", "participant_type": "CAST", "status": "PENDING" }
```

| `status` | 内容 |
|---|---|
| `REGISTERED` | その人物が、招待の役割でこの公演のACTIVEな参加者（招待の状態にかかわらず） |
| `PENDING` | 登録待ち（招待されたアドレスの確認が済むと登録される） |
| `EXPIRED` | 登録待ちのまま期限切れ |
| `CANCELLED` | 取消、または公演の終了・招待者の権限なしで無効 |
| `CLOSED` | そのほかの終了（以前に完了・回答済みで、今はACTIVEな参加者でない） |

メールアドレス・招待時の氏名・備考は返さない。

| HTTP | code | 条件 |
|---|---|---|
| 403 | `stageart_participant_invitation_forbidden` | 人物がない、またはアカウントがACTIVEでない |
| 404 | `stageart_participant_invitation_not_found` | 招待がない、IDの形式が違う、または本人宛てでない（区別しない） |
| 429 | `stageart_rate_limited` | 403・404の失敗が10分間に10回に達した |

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